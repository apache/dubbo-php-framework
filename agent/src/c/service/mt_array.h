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
#ifndef MT_ARRAY_H
#define MT_ARRAY_H
#include <stdlib.h>

typedef struct{
	void *elts;
	int nelts;
	size_t size;
	int nalloc;
}mt_array_t;

mt_array_t *mt_array_create(int nalloc,int size);
void* mt_array_push(mt_array_t *m);
void mt_array_destroy(mt_array_t *m);
#endif
