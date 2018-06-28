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
#ifndef FSOF_REDIS_H
#define FSOF_REDIS_H

struct fsof_message;

int fsof_redis_init(const char *path);
int fsof_redis_delete(const char *path);
//compare redis exists provider list with zookeeper exists provider list add by iceli
int compare_and_set_val(const struct fsof_message *message);
//directly set zookeeper exists provider list into redis when redis can't find exists provider list add by iceli
int fsof_redis_setval(const struct fsof_message *message);
struct redisReply* fsof_redis_get(const char *path);
void fsof_redis_close();


#endif
