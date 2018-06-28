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
#include  "fsof_mq.h"
#include <string.h>
#include <assert.h>

#define DEFAULT_QUEUE_SIZE (1024)

struct fsof_queue {
    int cap;
    int head;
    int tail;
    int lock;
    struct fsof_message *queue;
};

struct fsof_queue* fsof_mq_create() {
    struct fsof_queue *q = malloc(sizeof(struct fsof_queue));
    if (q == NULL) {
        //log info error malloc failed exit
        exit(-1);
    }
   
    q->cap = DEFAULT_QUEUE_SIZE;
    q->head = 0;
    q->tail = 0;
    q->lock = 0;
    q->queue = malloc(sizeof(struct fsof_message) * q->cap);
    if (q->queue == NULL) {
        //log info error malloc failed exit
        free(q);
        exit(-1);
    }
    
    return q;
}

//expand queue 
static void fsof_expand_queue(struct fsof_queue *q) {
    struct fsof_message *new_queue = malloc(sizeof(*new_queue) * q->cap * 2);
	
    int i;    
    for (i = 0; i < q->cap; i++) {
        new_queue[i] = q->queue[i];
    }

    q->head = 0;
    q->tail = q->cap;
    q->cap *= 2;
    free(q->queue);
    q->queue = new_queue;
}

void fsof_mq_push(struct fsof_queue *q,struct fsof_message *message) {
    assert(message);
    LOCK(q);

    q->queue[q->tail++] = *message;
    if (q->tail >= q->cap) {
        q->tail = 0;
    }

    if (q->head == q->tail) {
        fsof_expand_queue(q);
    }

    UNLOCK(q);
}

int fsof_mq_pop(struct fsof_queue *q,struct fsof_message *message) {
    int ret = 0;  
    LOCK(q);

    if (q->head != q->tail) {
        *message = q->queue[q->head++]; 
        ret = 1;
        
        if (q->head >= q->cap) {
            q->head = 0;
        }
    }
    
    UNLOCK(q);
    return ret;
}

int fsof_mq_len(struct fsof_queue *q) {
    int len = 0;
    LOCK(q);

    if (q->head <= q->tail) {
        len = q->tail - q->head;
    }else {
        len = q->tail + q->cap - q->head;
    }

    UNLOCK(q);
    return len;
}

int fsof_mq_clear(struct fsof_queue *q) {
	LOCK(q);
	UNLOCK(q);
	int i,j = 0;
	for (i = 0; i < q->cap; i++) {
		if (q->queue[i].key != NULL) {
			free(q->queue[i].key);
		}

		for (j = 0; j < q->queue[i].count; j++) {
			if (q->queue[i].value[j] != NULL) {
				free(q->queue[i].value[j]);
			}
		}
	}
	memset(q->queue,0,sizeof(struct fsof_message) * q->cap);
	return 0;
}

void fsof_mq_destroy(struct fsof_queue *q) {

    if(NULL != q) {
        if(NULL != q->queue) {
            int i,j = 0;
            for (i = 0; i < q->cap; i++) {
                if (q->queue[i].key != NULL) {
                    free(q->queue[i].key);
                }

                for (j = 0; j < q->queue[i].count; j++) {
                    if (q->queue[i].value[j] != NULL) {
                        free(q->queue[i].value[j]);
                    }
                }
            }
            free(q->queue);
        }
	 
        free(q);
    }
}
