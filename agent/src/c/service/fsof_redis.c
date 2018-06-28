/**
 * Licensed to the Apache Software Foundation (ASF) under one or more
 * contributor license agreements.  See the NOTICE file distributed with
 * this work for additional information regarding copyright ownership.
 * The ASF licenses this file to You under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with
 * the License.  You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */
#include "fsof_redis.h"
#include "fsof_mq.h"
#include "mt_array.h"
#include "fsof_log.h"
#include <hiredis/hiredis.h>  
#include <assert.h>
#include <string.h>
#include <unistd.h>
#include <netinet/in.h>

#define UNIX_PATH_LEN (100)

struct array_object {
    char *data_ptr;
};

extern char g_str_localip[INET_ADDRSTRLEN];
static redisContext* g_redis_conn = NULL;
static char unix_path[UNIX_PATH_LEN] = {0};

/*
* compare zookeeper provider list with redis provider list,then delete not exists list and add new provider list
*/
int compare_and_set_val(const struct fsof_message *message) {
    struct redisReply *object = NULL;
    struct redisReply *reply = fsof_redis_get(message->key);
    if (reply != NULL && reply->elements > 0 && message->count > 0) {
        //mt_array_t *same_list = mt_array_create(message->count,sizeof(struct array_object));
        mt_array_t *new_list = mt_array_create(message->count,sizeof(struct array_object));
        mt_array_t *delete_list = mt_array_create(message->count,sizeof(struct array_object));
        //assert(same_list != NULL);
        assert(new_list != NULL);
        assert(delete_list != NULL);

        struct array_object *tmp_object = NULL;
        int i = 0;
        int j = 0;
        for (; i < reply->elements; i++) {
            object = reply->element[i]; 
            if (object != NULL) {
                for (; j < message->count; j++) {
                    if (strcmp(object->str,message->value[j]) == 0) {//equal
                        break;
                    }
                } 

                if (j == message->count) {//not found need delete
                    tmp_object = (struct array_object*)mt_array_push(delete_list);
                    assert(tmp_object != NULL);
                    if (tmp_object != NULL) {
                        tmp_object->data_ptr = object->str;
                    }
                }

                if (j < message->count) { //found same value
                    //nothing to do
                }

                j = 0;
            }
        }

        i = j = 0;
        for (; i < message->count; i++) {
            for (; j < reply->elements; j++) {
                object = reply->element[j]; 
                if (strcmp(object->str,message->value[i]) == 0) {//equal
                    break;
                }
            }

            if (j == reply->elements) { // not found new value 
                tmp_object = (struct array_object*)mt_array_push(new_list);
                assert(tmp_object != NULL);
                if (tmp_object != NULL) {
                    tmp_object->data_ptr = message->value[i];
                }
            }

            j = 0;
        }

        if (delete_list->nelts > 0) {
            struct array_object *ptr = NULL;
            struct redisReply *tmp_reply = NULL;
            i = 0;
            for (; i < delete_list->nelts; i++) {
                ptr = (struct array_object*)(delete_list->elts + i * delete_list->size);
                fsof_log_info(INFO,"BEGIN Delete val is %s succeed,key is %s\n",ptr->data_ptr,message->key);
__DELETE_AGAIN:
                tmp_reply = redisCommand(g_redis_conn,"lrem %s 0 %s",message->key,ptr->data_ptr); //delete value
                if (tmp_reply == NULL) {
                    fsof_redis_close();
                    fsof_redis_init(unix_path);
                    goto __DELETE_AGAIN;
                }

                fsof_log_info(INFO,"Delete val is %s succeed,key is %s\n",ptr->data_ptr,message->key);
                freeReplyObject(tmp_reply);
            }
        }

        if (new_list->nelts > 0) {
            i = 0;
            struct array_object *ptr = NULL;
            struct redisReply *tmp_reply = NULL;
            for (; i < new_list->nelts; i++) {
                ptr = (struct array_object*)(new_list->elts + i * new_list->size);
__NEW_AGAIN:
                tmp_reply = redisCommand(g_redis_conn,"lpush %s %s",message->key,ptr->data_ptr);
                if (tmp_reply == NULL) {
                    fsof_redis_close();
                    fsof_redis_init(unix_path);
                    goto __NEW_AGAIN;
                }
                fsof_log_info(INFO,"Insert val is %s succeed,key is %s\n",ptr->data_ptr,message->key);
                freeReplyObject(tmp_reply);
            }
        }

        mt_array_destroy(delete_list);
        mt_array_destroy(new_list);
        //mt_array_destroy(same_list);
        freeReplyObject(reply);  
    } else {
        return fsof_redis_setval(message); //directly set new value list
    }
    return 0;
}

//set list into redis
int fsof_redis_setval(const struct fsof_message *message) {
    assert(message);
    int i = 0;
    struct redisReply *reply = NULL;


    if (message->count > 0) {
        for (i = 0; i < message->count; i++) {
            if (message->value[i] != NULL) {
__SET_AGAIN:
                reply = redisCommand(g_redis_conn,"lpush %s %s",message->key,message->value[i]);
                if (reply == NULL) {
                    fsof_redis_close();
                    fsof_redis_init(unix_path);
                    goto __SET_AGAIN;
                }

                freeReplyObject(reply);
            }
        }
    }
    
    return 0;
}

struct redisReply* fsof_redis_get(const char *path) {
    struct redisReply *reply = NULL;

__GET_AGAIN:
    reply = redisCommand(g_redis_conn,"lrange %s 0 -1",path);
    if (reply == NULL) {
        //occured an bad error ,need rejoin redis server
        fsof_redis_close();
        fsof_redis_init(unix_path);
        goto __GET_AGAIN;
    } else {
        if (reply->type != REDIS_REPLY_ARRAY) {  //value type must be array
            freeReplyObject(reply);  
            return NULL; 
        }
    }
    
    return reply;
}

int fsof_redis_delete(const char *path) {
    int err = 0;
    struct redisReply *reply = NULL;

__DELETE_AGAIN:
    reply = redisCommand(g_redis_conn,"del %s",path);
    if (reply == NULL) {
        //occured an bad error ,need rejoin redis server
        fsof_redis_close();
        fsof_redis_init(unix_path);
        goto __DELETE_AGAIN;
    } else {
        freeReplyObject(reply);
    }
    
    return err;
}

void fsof_redis_close() {
    redisFree(g_redis_conn);
}

int fsof_redis_init(const char *path) {
    int ret = 0;
    struct timeval start = {0};
    struct timeval end = {0};
    int exec_time = 0;
    struct timeval time;

    time.tv_sec = 0;
    time.tv_usec = 60;

    do {
        gettimeofday(&start,NULL);
        g_redis_conn = redisConnectUnixWithTimeout(path,time);
        gettimeofday(&end,NULL);
        exec_time = (end.tv_sec - start.tv_sec) * 1000000 + (end.tv_sec - start.tv_sec); //calculate execute time
        ret = g_redis_conn->err;

        if (ret > 0) {
            fsof_log_info(INFO,"%s|FSOF_AGENT|%d|connect redis timeout!|connect_redis|%d",g_str_localip,ret,exec_time);
            sleep(300);
        }
    }while(ret);

    strcpy(unix_path,path);
    fsof_log_info(INFO,"%s|FSOF_AGENT|%d|connect redis succeed!|connect_redis|%d",g_str_localip,ret,exec_time);
    return 0;
}

