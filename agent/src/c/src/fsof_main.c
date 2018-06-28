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
#include <stdlib.h>
#include <stdio.h>
#include <string.h>
#include <pthread.h>
#include <stdbool.h>
#include <unistd.h>
#include <assert.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <ifaddrs.h>
#include <hiredis/hiredis.h>  
#include "../service/fsof_mq.h"
#include "../service/mt_array.h"
#include "../service/fsof_zookeeper.h"
#include "../common/fsof_global.h"
#include "../common/fsof_util.h"
#include "../service/fsof_redis.h"
#include "../service/fsof_log.h"
#include "../common/fsof_url.h"
#include <sys/fcntl.h>
#include <signal.h>

//redis thread handle
pthread_t g_redis_pid;
//message queue ,store zookeeper node info
struct fsof_queue *g_message_queue;
//current zookeeper config path
static char g_zookeeper_conf[MAX_CONFIG_PATH_LEN] = {0};
//zookeeper url list
static char g_zk_list[512] = {0};
char g_str_localip[INET_ADDRSTRLEN] = {0};

void init_zookeeper_env();
static void set_all_providers_watcher(struct String_vector *list); //all providers add watcher 
static void enum_provider_list(struct fsof_message *message);
static void get_zk_root_children();

//deal with signo  such as SIGALARM
void sigroutine(int signo) {
    //need to get zookeeper node info  from zk server manually
    if (signo == SIGALRM) { 
        fsof_log_info(ERROR,"Now recvied alarm signal\n");
        get_zk_root_children();
    }
}

//create thread
static void create_thread(pthread_t *thread,void *(*start_routine) (void *),void *arg) {
    if (pthread_create(thread,NULL,start_routine,arg)) {
        //thread create failed
        fsof_log_info(INFO,"create thread function called failed!\n");
        exit(-1);
    }
}

//redis worker thread ,main job is getting message from g_message_queue ,then write into redis 
static void * thread_redis_work(void *p) {
    int ret = 0;
    int i = 0;
    struct fsof_message message = {0};
    struct timeval start = {0};
    struct timeval end = {0};
    int exec_time = 0;
	
    while(true) {
        //pop message from g_message_queue
        ret = fsof_mq_pop(g_message_queue,&message);
        if (ret) {
            //deal with message
            if (message.count > 0) {
                gettimeofday(&start,NULL);
                ret = compare_and_set_val(&message);
                gettimeofday(&end,NULL);
                exec_time = (end.tv_sec - start.tv_sec) * 1000000 + (end.tv_usec - start.tv_usec); //calculate execute time
                if (ret == 0) { 
                    //insert succeed
                    fsof_log_info(INFO,"%s|FSOF_AGENT|%d|set redis data succeed!|set_redis|%d",g_str_localip,0,exec_time);
                } 
            } else {
                gettimeofday(&start,NULL);
                fsof_redis_delete(message.key);
                gettimeofday(&end,NULL);
                exec_time = (end.tv_sec - start.tv_sec) * 1000000 + (end.tv_usec - start.tv_usec); //calculate execute time
                fsof_log_info(DEBUG,"%s|FSOF_AGENT|%d|delete redis data  key succeed!|delete_redis|%d",g_str_localip,0,exec_time);
            }
            //enum every provider value and key
            enum_provider_list(&message);
            //free message content, message itself free by g_message_queue
            for (i = 0; i < message.count; i++) {
                if (message.value[i] != NULL) {
                    free(message.value[i]);
                }
            }

            free(message.key);
            if (message.value != NULL) {
                free(message.value);
            }
        } else {
            //0.1 seconds
            usleep(100000);
        }

    }
    
    return NULL;
}

static void parse_provider_key(struct String_vector *list,const char *path) {
    int i ;
    struct fsof_message message  = {0};
    char url_encode_data[URL_ENCODE_BUF_LEN] = {0};
    int nlen = 0;
    int service_len = 0;
    int provider_index = 0;
    char  *provider_str = NULL;
 
    //get service key
    provider_str = strstr(path,PROVIDER_NAME);
    provider_index = provider_str - path;
    service_len = provider_index - sizeof(FSOF_ROOT_NAME);
    if (service_len <= 0) {
        return;
    }

    message.key = malloc(service_len + 1);
    assert(message.key);
    memcpy(message.key,path + sizeof(FSOF_ROOT_NAME),service_len);
    message.key[service_len] = '\0';

    if (list->count > 0) {
        message.value = malloc(sizeof(char*) * list->count);
        assert(message.value);
        memset(message.value,0,sizeof(char*) * list->count);

        int val_index = 0;
        for (i = 0; i < list->count; i++) {
            memcpy(url_encode_data,list->data[i],strlen(list->data[i]));
            nlen = fsof_url_decode(url_encode_data,strlen(url_encode_data));
            fsof_log_info(INFO,"key: %s,value: %s,nlen is %d\n",message.key,url_encode_data,nlen);
			message.value[val_index] = malloc(nlen + 1);
			assert(message.value[val_index]);
			strcpy(message.value[val_index],url_encode_data);
			message.value[val_index][nlen] = '\0';
			val_index++;
            memset(url_encode_data,0,URL_ENCODE_BUF_LEN);
        }

        message.count = val_index;

    }else {
        fsof_log_info(INFO,"parse key  child is zero,need to delete redis key %s\n",path);
        //means delete provider key
        message.count = 0; 
    }

    fsof_mq_push(g_message_queue,&message);

}

//enum provider list
static void enum_provider_list(struct fsof_message *message) {
    int i ;

    for (i = 0; i < message->count; i++) {
        fsof_log_info(INFO,"Enum provider key value is %s,path is %s\n",message->value[i],message->key);
    }
}

static void fsof_provider_node_watcher(zhandle_t* zh, int type, int state,
                                                 const char* path, void* watcherCtx) {
    struct String_vector *str_list = NULL;
    struct timeval start = {0};
    struct timeval end   = {0};
    int exec_time = 0;

    if (type == ZOO_CHILD_EVENT) {

		fsof_log_info(INFO,"%s children node has been changed ,need to get new children list\n",path);
        if (strcmp(path,FSOF_ROOT_NAME) == 0) {
            gettimeofday(&start,NULL);
            str_list = fsof_zk_get_children(FSOF_ROOT_NAME);
            gettimeofday(&end,NULL);
            exec_time = (end.tv_sec - start.tv_sec) * 1000000 + (end.tv_usec - start.tv_usec); //calculate execute time

            if (str_list != NULL) {
                fsof_log_info(INFO,"%s|FSOF_AGENT|%d|get zookeeper data succeed!|get_zookeeper|%d",g_str_localip,0,exec_time);
                set_all_providers_watcher(str_list);
            } else {
                fsof_log_info(INFO,"%s|FSOF_AGENT|%d|get zookeeper data failed!|get_zookeeper|%d",g_str_localip,-1,exec_time);
            }
        } else {
            gettimeofday(&start,NULL);
            str_list = fsof_zk_get_children(path);
            gettimeofday(&end,NULL);
            exec_time = (end.tv_sec - start.tv_sec) * 1000000 + (end.tv_usec - start.tv_usec); //calculate execute time

            if (str_list != NULL) {
                fsof_log_info(INFO,"%s|FSOF_AGENT|%d|get zookeeper data succeed!|get_zookeeper|%d",g_str_localip,0,exec_time);
                parse_provider_key(str_list,path);
            } else {
                fsof_log_info(INFO,"%s|FSOF_AGENT|%d|get zookeeper data failed!|get_zookeeper|%d",g_str_localip,-1,exec_time);
            }
        }
		
        if (str_list != NULL) {
            deallocate_String_vector(str_list);
            free(str_list);
        }		
    }
}

//watcher service node ,when the service node children has been changed ,need to set provider watcher
static void fsof_service_node_watcher(zhandle_t* zh, int type, int state,
                                                const char* path, void* watcherCtx) {
    struct String_vector *str_list = NULL;
    struct String_vector *provider_list = NULL;
    struct timeval start = {0};
    struct timeval end   = {0};
    int exec_time = 0;
    char provider_key[512] = {0};
    int i;

    if (type == ZOO_CHILD_EVENT) {
            fsof_log_info(INFO,"service node watcher  entered %s\n",path);
            gettimeofday(&start,NULL);
            str_list = fsof_zk_get_children(path);
            gettimeofday(&end,NULL);
            exec_time = (end.tv_sec - start.tv_sec) * 1000000 + (end.tv_usec - start.tv_usec); //calculate execute time

            if (str_list != NULL) {
                sprintf(provider_key,"%s%s",path,PROVIDER_NAME);
                fsof_log_info(INFO,"%s|FSOF_AGENT|%d|get zookeeper data succeed!|get_zookeeper|%d",g_str_localip,0,exec_time);

                for (i = 0; i < str_list->count; i++) {
                    if (strcmp(str_list->data[i],PROVIDER_NODE_NAME) == 0) {
                        fsof_zk_add_listener(provider_key,fsof_provider_node_watcher);
                        gettimeofday(&start,NULL);
                        provider_list = fsof_zk_get_children(provider_key); 
                        gettimeofday(&end,NULL);
                        exec_time = (end.tv_sec - start.tv_sec) * 1000000 + (end.tv_usec - start.tv_usec); //calculate execute time

                        if (provider_list != NULL) { //deal 
                            //enum_provider_list(provider_list,provider_key);
                            parse_provider_key(provider_list,provider_key);
                            deallocate_String_vector(provider_list);
                            free(provider_list);
                            fsof_log_info(INFO,"%s|FSOF_AGENT|%d|get zookeeper data succeed!|get_zookeeper|%d",g_str_localip,0,exec_time);
                        } else {
                            fsof_log_info(INFO,"%s|FSOF_AGENT|%d|get zookeeper data failed!|get_zookeeper|%d",g_str_localip,-1,exec_time);
                        }
                        break;
                    }
                } 

                deallocate_String_vector(str_list);
                free(str_list);
            } else {
                fsof_log_info(INFO,"%s|FSOF_AGENT|%d|get zookeeper data failed!|get_zookeeper|%d",g_str_localip,-1,exec_time);
            }
    }

}

static void set_all_providers_watcher(struct String_vector *list) {
    struct String_vector *provider_list = NULL;
    char provider_key[512] = {0};
    struct timeval start = {0};
    struct timeval end   = {0};
    int exec_time = 0;
    int i;

    for (i = 0; i < list->count; i++) {
        sprintf(provider_key,"%s/%s%s",FSOF_ROOT_NAME,list->data[i],PROVIDER_NAME);
        fsof_zk_add_listener(provider_key,fsof_provider_node_watcher);
        gettimeofday(&start,NULL);
        provider_list = fsof_zk_get_children(provider_key); 
        gettimeofday(&end,NULL);
        exec_time = (end.tv_sec - start.tv_sec) * 1000000 + (end.tv_usec - start.tv_usec); //calculate execute time
        
        if (provider_list == NULL) {
            fsof_log_info(INFO,"%s|FSOF_AGENT|%d|get zookeeper data failed!|get_zookeeper|%d",g_str_localip,-1,exec_time);
            memset(provider_key,0,sizeof(provider_key));
            sprintf(provider_key,"%s/%s",FSOF_ROOT_NAME,list->data[i]);
            fsof_zk_add_listener(provider_key,fsof_service_node_watcher);
            gettimeofday(&start,NULL);
            provider_list = fsof_zk_get_children(provider_key);
            gettimeofday(&end,NULL);
            exec_time = (end.tv_sec - start.tv_sec) * 1000000 + (end.tv_usec - start.tv_usec); //calculate execute time

            if (provider_list == NULL) {
                fsof_log_info(INFO,"%s|FSOF_AGENT|%d|get zookeeper data failed!|get_zookeeper|%d",g_str_localip,-1,exec_time);
            } else {
                fsof_log_info(INFO,"%s|FSOF_AGENT|%d|get zookeeper data succeed!|get_zookeeper|%d",g_str_localip,0,exec_time);
            }
        }else {
            //enum_provider_list(provider_list,provider_key);
            fsof_log_info(INFO,"%s|FSOF_AGENT|%d|get zookeeper data succeed!|get_zookeeper|%d",g_str_localip,0,exec_time);
            parse_provider_key(provider_list,provider_key);
        }

        memset(provider_key,0,sizeof(provider_key));
        if (provider_list != NULL) {
            deallocate_String_vector(provider_list);
            free(provider_list);
        }
    }    
}
static void get_zk_root_children() {
    struct timeval start = {0};
    struct timeval end   = {0};
    int exec_time = 0;
    struct String_vector *str_list = NULL;

    fsof_zk_add_listener(FSOF_ROOT_NAME,fsof_provider_node_watcher);
    gettimeofday(&start,NULL);
    str_list = fsof_zk_get_children(FSOF_ROOT_NAME);
    gettimeofday(&end,NULL);
    exec_time = (end.tv_sec - start.tv_sec) * 1000000 + (end.tv_usec - start.tv_usec); //calculate execute time

    if (str_list != NULL) {
        fsof_log_info(INFO,"%s|FSOF_AGENT|%d|get zookeeper data succeed!|get_zookeeper|%d",g_str_localip,0,exec_time);
        set_all_providers_watcher(str_list);
    }else {
        fsof_log_info(INFO,"%s|FSOF_AGENT|%d|get zookeeper data failed!|get_zookeeper|%d",g_str_localip,-1,exec_time);
        fsof_log_info(INFO,"can't get children of key %s\n",FSOF_ROOT_NAME);
    }

    if (str_list != NULL) {
        deallocate_String_vector(str_list);
        free(str_list);
    }
}

//watcher fsof root node  main event :ZOO_CONNECTED_STATE ZOO_EXPIRED_SESSION_STATE
static void fsof_root_node_watcher(zhandle_t* zh, int type, int state,
                                            const char* path, void* watcherCtx) {
    if (type == ZOO_SESSION_EVENT) {
        if(state == ZOO_CONNECTED_STATE) {
            //begin get children from root node
            get_zk_root_children();
        } else if (state == ZOO_EXPIRED_SESSION_STATE  || state == ZOO_CONNECTING_STATE || state == ZOO_AUTH_FAILED_STATE) {
            //log info  sesstion expired need rejoin
            fsof_log_info(ERROR,"zookeeper session expired,need rejoin zookeeper\n");
            fsof_zk_close();
            init_zookeeper_env();
        }
    }
}

void parse_zk_config(char *src,char *dest) {
    assert(src);
    assert(dest);
    char *ptr = NULL;
    char *tail_ptr = src + strlen(src);

    while ((ptr = strstr(src,"http://")) != 0) { 
        if ((ptr - src) == 0) {
            src += strlen("http://");
        }
        else if ((ptr - src) > 0) {
            memcpy(dest,src,ptr - src);
            dest += ptr - src;
            src += ptr - src + strlen("http://");
        }
    }

    if (ptr == NULL && src != tail_ptr) {
        memcpy(dest,src,tail_ptr - src);
    }
}

void init_zookeeper_env() {
   int ret = 0;
   char *zk_str = NULL;
   struct timeval start = {0};
   struct timeval end   = {0};
   int exec_time = 0;

   if (*g_zookeeper_conf != '\0') { 
        //parse zookeeper config
        fsof_log_info(INFO,"Now begin start parse zookeeper config file %s!\n",g_zookeeper_conf);
        zk_str = get_ini_key_string("fsof_setting","zk_url_list",g_zookeeper_conf);
        parse_zk_config(zk_str,g_zk_list);
        fsof_log_info(INFO,"Now end start parse zookeeper config file %s,zk list str is %s!\n",g_zookeeper_conf,g_zk_list);
   }
   //set  root node watcher
   fsof_zk_add_state_listener(fsof_root_node_watcher);

   do{
        gettimeofday(&start,NULL);
        ret = fsof_zk_init(g_zk_list);
        gettimeofday(&end,NULL);
        exec_time = (end.tv_sec - start.tv_sec) * 1000000 + (end.tv_sec - start.tv_sec); //calculate execute time
         
        if (ret > 0) {
            fsof_log_info(INFO,"%s|FSOF_AGENT|%d|connect zookeeper timeout!|connect_zookeeper|%d",g_str_localip,ret,exec_time);
            sleep(300);
        }
    }while(ret);
   
    fsof_log_info(INFO,"%s|FSOF_AGENT|%d|connect zookeeper succeed!|connect_zookeeper|%d",g_str_localip,ret,exec_time);
}

static void get_local_ip() {
    struct ifaddrs * ifAddrStruct = NULL;
    void * tmpAddrPtr = NULL;

    getifaddrs(&ifAddrStruct);
    while (ifAddrStruct != NULL) {
        
        if (ifAddrStruct->ifa_addr->sa_family == AF_INET) {
            tmpAddrPtr = &((struct sockaddr_in *)ifAddrStruct->ifa_addr)->sin_addr; 
            inet_ntop(AF_INET, tmpAddrPtr, g_str_localip, INET_ADDRSTRLEN);
        }

        ifAddrStruct = ifAddrStruct->ifa_next;
        memset(g_str_localip,0,INET_ADDRSTRLEN);
    }
}

void cleanup_env() {
   fsof_zk_close();
   fsof_redis_close();
   fsof_log_close();
}

//set process to daemon 
void daemonize(void) {
    int fd;

    /* parent exits */
    if (fork() != 0) {
        exit(0); 
    }

    /* create a new session */	
    setsid();

    /* Every output goes to /dev/null. If Redis is daemonized but
     * the 'logfile' is set to 'stdout' in the configuration file
     * it will not log at all. */
    if ((fd = open("/dev/null", O_RDWR, 0)) != -1) {
        dup2(fd, STDIN_FILENO);
        dup2(fd, STDOUT_FILENO);
        dup2(fd , STDERR_FILENO);
		
		close(fd);
    }
}

int main(int argc,char **argv) {
    int err = 0;
    struct itimerval timer_setting,old_val;
    int timer_val = 0;

    if (argc < 2){
        fprintf(stderr,"parameters contains the timer value of zookeeper unit:second and the URL config of zookeeper!\n");
        fprintf(stderr,"agent time conf/fsof.ini\n");
        exit(0);
    }
    
    //parameter contains the timer value of zookeeper unit:second
    timer_val = atoi(argv[1]);
    
    //dubbo-php-framework/config/global/conf/fsof.ini
    strncpy(g_zookeeper_conf, argv[2],  sizeof(g_zookeeper_conf) - 1 > strlen(argv[2]) ? strlen(argv[2]) : sizeof(g_zookeeper_conf) - 1);
    if (access(g_zookeeper_conf, F_OK) == -1){
        fprintf(stderr,"can not find conf/fsof.ini\n");
        exit(0);
    }
    
    //set process to daemon ,if failed ,directly exit
    daemonize();
    signal(SIGHUP, SIG_IGN);
    signal(SIGPIPE, SIG_IGN);
    signal(SIGALRM, sigroutine);

    if (timer_val > 0) {
        timer_setting.it_value.tv_sec = timer_val;
        timer_setting.it_value.tv_usec = 0;
        timer_setting.it_interval.tv_sec = timer_val;
        timer_setting.it_interval.tv_usec = 0;
        setitimer(ITIMER_REAL, &timer_setting, &old_val);   
    }
    
    err = fsof_log_init();
    if (err > 0) {
        fprintf(stderr,"agent log init failed,exit!\n");
        exit(-1);
    }

    get_local_ip();
    g_message_queue = fsof_mq_create();
    create_thread(&g_redis_pid,thread_redis_work,NULL);
    fsof_redis_init(REDIS_UNIX_SOCK);
    init_zookeeper_env();

    pthread_join(g_redis_pid,NULL); 
    fsof_mq_destroy(g_message_queue);
    fsof_log_info(INFO,"fsof agent redis thread exit,need cleanup resource!\n");
    
    //cleanup
    cleanup_env();
   
    return 0;
}

