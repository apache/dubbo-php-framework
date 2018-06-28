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
#include "fsof_log.h"
#include <zlog.h>
#include <time.h>
#include <string.h>
#include "../common/fsof_util.h"


#define LOG_INFO_MAX_LEN  (8 * 1024)
#define LOG_DEBUG_INFO_NAME "fsof_agent_log"

static zlog_category_t *g_zc;
static char g_zlog_conf[MAX_CONFIG_PATH_LEN] = {0};


void fsof_log_info(enum log_level level,const char *format,...) {
    char log_info[LOG_INFO_MAX_LEN] = {0};
    va_list v_log;

    va_start(v_log,format);
    vsnprintf(log_info,LOG_INFO_MAX_LEN,format,v_log);
    va_end(v_log);
    
    if (g_zc != NULL) {
        switch (level) {
            case INFO:
                zlog_info(g_zc,log_info);
                break;
            case ERROR:
                zlog_error(g_zc,log_info);
                break;
            case DEBUG:
                zlog_debug(g_zc,log_info);
        }
    }
}

void fsof_log_close() {
    if (g_zc != NULL) {
        zlog_fini();
        g_zc = NULL;
    }
}

int fsof_log_init() {
    int err = 0;

    get_current_path(g_zlog_conf,"agent_log.conf",1);
    err = zlog_init(g_zlog_conf);;
    if (err > 0) {
        return err;
    }

    g_zc = zlog_get_category("fsof_agent");
    if (g_zc == NULL) {
        zlog_fini();
        err = 1;
        return err;
    }

    fsof_log_info(INFO,"log init succeed--------------!!!\n");

    return 0; //
}
