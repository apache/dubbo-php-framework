#!/bin/bash

cd $(dirname $0) 
agent_server=$(ps aux | grep 'release/agent_server' | grep -v grep| wc -l)
if [ "$agent_server" -eq "0" ]; then
    /bin/bash  start_agent_server.sh
fi
