#!/bin/bash

log_dir="../../../../../dubbo-php-framework/agent/log/agent"

if [ ! -d $log_dir ]; then
    mkdir -p $log_dir
fi

process=$(ps aux  | grep -v "grep" | grep "release/agent_server" | wc -l) 
if [ "$process" -ne "0" ]; then
       ps aux |grep -v "grep" |grep "release/agent_server"|awk '{print $2}'|xargs kill -9
fi

cd ../../../../../dubbo-php-framework/agent/src/c/release
cp agent agent_server
chmod  u+x agent_server
cur_dir=$(pwd)
$cur_dir/agent_server 600 ../../../../../dubbo-php-framework/config/global/conf/fsof.ini
