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
#ifndef FSOF_ZOOKEEPER_H
#define FSOF_ZOOKEEPER_H
#include "fsof_mq.h"
#include <stdbool.h>
#include <zookeeper/zookeeper.h>


typedef void (*watcher_fn)(zhandle_t* zh, int type, int state,
                const char* path, void* watcherCtx);
void fsof_zk_create(const char *path,bool ephemeral);

int fsof_zk_delete(const char *path);

struct String_vector * fsof_zk_get_children(const char *path);

void fsof_zk_add_listener(const char *path,watcher_fn watcher);

void fsof_zk_remove_listener(const char *path,watcher_fn watcher);

void fsof_zk_add_state_listener(watcher_fn watcher);

void fsof_zk_remove_state_listener(watcher_fn watcher);

int fsof_zk_init(const char *host);

void fsof_zk_close();


#endif
