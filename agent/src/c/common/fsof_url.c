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
#include "fsof_url.h"
#include <stdlib.h>
#include <ctype.h>

char * fsof_url_encode(char const *s, int len, int *new_length) {
    unsigned char const *from, *end;
    unsigned char c;
    from = (unsigned char const*)s;
    end = (unsigned char const*)s + len;
    unsigned char *to = (unsigned char *) malloc(3 * len + 1);
    unsigned char *start = to;

    unsigned char hexchars[] = "0123456789ABCDEF";

    while (from < end) {
        c = *from++;

        if (c == ' ') {
            *to++ = '+';
        } else if ((c < '0' && c != '-' && c != '.')
                             ||(c < 'A' && c > '9')
                             ||(c > 'Z' && c < 'a' && c != '_')
                             ||(c > 'z')) {
            to[0] = '%';
            to[1] = hexchars[c >> 4];
            to[2] = hexchars[c & 15];
            to += 3;
        } else {
            *to++ = c;
        }
    }

    *to = 0;
    if (new_length) {
        *new_length = to - start;
    }

    return (char *) start;
}

int fsof_url_decode(char *str, int len)
{
    char *dest = str;
    char *data = str;
    int value;
    int c;

    while (len--) {
        if (*data == '+') {
            *dest = ' ';
        }
        else if (*data == '%' && len >= 2 && isxdigit((int) *(data + 1))
                                 && isxdigit((int) *(data + 2))) {
            c = ((unsigned char *)(data+1))[0];
            if (isupper(c)) {
                c = tolower(c);
            }

            value = (c >= '0' && c <= '9' ? c - '0' : c - 'a' + 10) * 16;
            c = ((unsigned char *)(data+1))[1];
            if (isupper(c)) {
                c = tolower(c);
            }

            value += c >= '0' && c <= '9' ? c - '0' : c - 'a' + 10;
            *dest = (char)value ;
            data += 2;
            len -= 2;
        } else {
            *dest = *data;
        }
        data++;
        dest++;
    }

    *dest = '\0';
    return dest - str;

}


