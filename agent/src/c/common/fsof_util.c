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
#include <fcntl.h>
#include <unistd.h>
#include "fsof_util.h"
#include <string.h>
#include <stdio.h>

char * trim(char * src) {
    int i = 0;
    char *begin = src;
    while(src[i] != '\0') {
        if(src[i] != ' ') {
            break;
        }else {
            begin++;
        }
        i++;
    }

    for(i = strlen(src)-1; i >= 0;  i--){
        if(src[i] != ' '){
            break;
        }else {
            src[i] = '\0';
        }
    }

    return begin;
}

char *get_ini_key_string(char *title,char *key,char *filename) {
    FILE *fp;
    char szLine[1024]; 
    static char tmpstr[1024];  
    char *ret_str = NULL;
    int rtnval;  
    int i = 0;   
    int flag = 0;  
    char *tmp;  
    
    if((fp = fopen(filename, "r")) == NULL)   
    {   
        printf("have   no   such   file \n");  
        return "";   
    }  

    while(!feof(fp))   
    {   
        rtnval = fgetc(fp);   
        if(rtnval == EOF)   
        {   
            break;   
        }   
        else   
        {   
            szLine[i++] = rtnval;   
        }   
        if(rtnval == '\n')   
        {   
            szLine[--i] = '\0';  
            i = 0;   
            tmp = strchr(szLine, '=');   
                                      
            if(( tmp != NULL )&&(flag == 1)) { 
                 if(strstr(szLine,key)!=NULL)   
                 {   
                    if ('#' == szLine[0])  
                    {  
                    }  
                    else if ( '/' == szLine[0] && '/' == szLine[1] )  
                    {  
                                                                                                                                       
                    }  
                    else  
                    {  
                        strcpy(tmpstr,tmp+1);   
                        fclose(fp);  
                        ret_str = trim(tmpstr);
                        return ret_str;   
                    }  
                 }  
            }
            else {
                strcpy(tmpstr,"[");   
                strcat(tmpstr,title);   
                strcat(tmpstr,"]");  
                if( strncmp(tmpstr,szLine,strlen(tmpstr)) == 0 )   
                {  
                    flag = 1;   
                }  
            }
        }
    }

    fclose(fp);   
    return ""; 
                       
}


int get_current_path(char buf[],char *pFileName,int depth) {
    char pidfile[64] = {0};  
    char *p = NULL;
    int bytes = 0;  
    int fd = 0;  
    int cur_dp = 0;

    sprintf(pidfile, "/proc/%d/cmdline", getpid());  
    fd = open(pidfile, O_RDONLY, 0);  
    bytes = read(fd, buf, 256);  
    close(fd);

    p = &buf[strlen(buf)];  
_AGAIN:
    while ('/' != *p) {
        *p = '\0';
        if ((p - buf) == 0) {
            return  1; //path now null,can't get depth dir
        }
        p--;
    }

    cur_dp++;
    if (cur_dp < depth) {
        p--;
        goto  _AGAIN;
    }
    p++;

    memcpy(p,pFileName,strlen(pFileName));  
	p += strlen(pFileName);
	*p = '\0';
    return 0;
}
