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
#ifndef FSOF_MESSAGE_QUEUE_H
#define FSOF_MESSAGE_QUEUE_H
#include <stdlib.h>
#include <stdint.h>

#define LOCK(q) while (__sync_lock_test_and_set(&(q)->lock,1)) {}
#define UNLOCK(q) __sync_lock_release(&(q)->lock);

struct fsof_message {
    char *key;
    char **value;
    int count; //total providers count
};

struct fsof_queue;

//push data into message queue
void fsof_mq_push(struct fsof_queue *q,struct fsof_message *message);
//pop data  from message queue
int fsof_mq_pop(struct fsof_queue *q,struct fsof_message *message);
//message queue len
int  fsof_mq_len(struct fsof_queue *q);
//message queue struct create
struct fsof_queue* fsof_mq_create();
//clear message
int fsof_mq_clear(struct fsof_queue *q);
//message queue destroy
void fsof_mq_destroy(struct fsof_queue *q);

#endif
