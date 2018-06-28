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
#include "mt_array.h"
#include "string.h"

static int mt_array_init(mt_array_t *m,int nalloc,int size);

mt_array_t *mt_array_create(int nalloc,int size) {
	mt_array_t *m = malloc(sizeof(mt_array_t));	
	if (m == NULL) { //failed
		return NULL;
	}
	int ret = mt_array_init(m,nalloc,size);
	if (ret == -1) {
		return NULL;
	}
	return m;
}

void *mt_array_push(mt_array_t *m) {
	size_t size;
	void *new,*elt;
	if (m->nalloc == m->nelts) {//full
		size = m->size * m->nalloc;
		new = malloc(size * 2);	
		if (new == NULL) {
			return NULL;
		}
		memcpy(new,m->elts,size);
		free(m->elts);
		m->elts = new;
		m->nalloc *= 2;
	}

	elt = (char*)m->elts + m->nelts * m->size;
	m->nelts++;
	return elt;

}

void mt_array_destroy(mt_array_t *m) {
	if (m->elts != NULL) {
		free(m->elts);
		free(m);
	}

}

static int mt_array_init(mt_array_t *m,int nalloc,int size) {
	m->nelts = 0;
	m->size = size;
	m->nalloc = nalloc;
	m->elts = malloc(nalloc * size);
	if (m->elts == NULL) {
		return -1;
	}
	return 0;
}
