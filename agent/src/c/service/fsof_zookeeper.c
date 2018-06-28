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
#include "fsof_zookeeper.h"
#include "../common/strmap.h"
#include "../common/fsof_global.h"
#include <assert.h>
#include <errno.h>

#define TIME_OUT 30000
#define ZOOKEEPER_VERSION -1

extern int errno;

struct provider_callback {
    watcher_fn watcher_cb;
};


static zhandle_t* g_zkhandle = NULL;
static watcher_fn g_watcher_fn; 
struct StrMap *g_provider_map;


void fsof_zk_create(const char *path,bool ephemeral) {
    //nothing to do
}

int fsof_zk_delete(const char *path) {
    int ret;
    ret = zoo_delete(g_zkhandle,path,-1);
    
    return ret;
}

struct String_vector * fsof_zk_get_children(const char *path) {
    int ret = -1;
    struct map_object *object_val = NULL;
    struct provider_callback *cb = NULL;
    struct String_vector *str_list = NULL;

    if (g_zkhandle == NULL) {
        return NULL;
    }

    str_list = malloc(sizeof(*str_list));
    if (str_list == NULL) {
        //log error
        exit(-1);
    }
	
    memset(str_list, 0, sizeof(*str_list));
    sm_get(g_provider_map,path,&object_val); 
   
    if (object_val != NULL && object_val->status == 1) { //succeed
        if (object_val->type == MAP_OBJECT_CB_TYPE) {
            cb = object_val->ptr;
            ret = zoo_wget_children(g_zkhandle,path,cb->watcher_cb,NULL,str_list);
        }
    } else { //0 error,can't get provider callback
        ret = zoo_wget_children(g_zkhandle,path,NULL,NULL,str_list);
    }

    if (ret < 0) {
        //log error 
        free(str_list);
        return NULL;
    }

    return str_list;
}

void fsof_zk_add_listener(const char *path,watcher_fn watcher) {
    struct provider_callback *cb = NULL;
    struct map_object *object_val = NULL;

    if (sm_exists(g_provider_map,path)) { //exist
        sm_get(g_provider_map,path,&object_val);
        cb = (struct provider_callback*)object_val->ptr;
        if (cb != NULL) {
            if (cb->watcher_cb != watcher) { //not same
                cb->watcher_cb = watcher;
            }
            object_val->status = 1;
        }

    } else { //create new one
        cb = malloc(sizeof(*cb));
        assert(cb);
        object_val = malloc(sizeof(*object_val));
        assert(object_val);
        cb->watcher_cb = watcher;
        object_val->type = MAP_OBJECT_CB_TYPE;
        object_val->ptr  = cb;
        object_val->status  = 1;
        sm_put(g_provider_map,path,object_val);
    }
}

void fsof_zk_remove_listener(const char *path,watcher_fn watcher) {
    struct provider_callback *cb = NULL;
    struct map_object *object_val = NULL;

    if (sm_exists(g_provider_map,path)) { //exist
        sm_get(g_provider_map,path,&object_val);
        if (object_val != NULL) {
            cb = (struct provider_callback*)object_val->ptr;
            if (cb != NULL && cb->watcher_cb == watcher) {
                object_val->status = 0;
            }
        }
    }
}

void fsof_zk_add_state_listener(watcher_fn watcher) {
    g_watcher_fn = watcher;
}

void fsof_zk_remove_state_listener(watcher_fn watcher) {
    g_watcher_fn = NULL;
}

int fsof_zk_init(const char *host) {
    int err = 0;

    if (g_watcher_fn == NULL) {
        //log error
        exit(-1);
    }

    g_zkhandle = zookeeper_init(host,g_watcher_fn,TIME_OUT, 0, "hello zookeeper.", 0);
    if (g_zkhandle == NULL) {
        //log error
        err = errno; //failed 
        return err;
    }

    g_provider_map = sm_new(DEFAULT_MAP_COUNT);
    if (g_provider_map == NULL) {
        //log error malloc failed
        exit(-1);
    }   

    return err;
}

void fsof_zk_close() {
    if (g_zkhandle != NULL) {
        zookeeper_close(g_zkhandle);

        if (g_provider_map != NULL) {
            sm_delete(g_provider_map);
            g_provider_map = NULL;
        }

        g_zkhandle = NULL;
    }
}
