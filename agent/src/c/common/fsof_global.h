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
#ifndef FSOF_GLOBAL_H
#define FSOF_GLOBAL_H

#define FSOF_ROOT_NAME "/dubbo"
#define PROVIDER_NAME "/providers"
#define PROVIDER_NODE_NAME "providers"
#define CONSUMER_NAME "/consumers"
#define ROUTERS_NAME  "/routers"
#define CONFIGUREATORS_NAME "/configurators"
#define CONFIGUREATORS_NODE_NAME "configurators"
#define FSOF_REDIS_CONFIGURATOR_KEY_NAME "override"

#define ZOOKEEPER_HOST "127.0.0.1:2181"
#define DEFAULT_MAP_COUNT (10)
#define REDIS_UNIX_SOCK  "/var/fsof/redis.sock"



struct list {
    char *value;
    struct list *next;
};

#endif
