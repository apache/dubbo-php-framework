TARGET = release/agent
CFLAGS = -g -Wall -lpthread -lzookeeper_mt   -L./lib -lhiredis -lzlog  -I./include
CC = gcc
$(TARGET):service/fsof_mq.c src/fsof_main.c service/fsof_zookeeper.c common/strmap.c service/fsof_redis.c service/fsof_log.c common/fsof_url.c common/fsof_util.c service/mt_array.c
	$(CC) $^ $(CFLAGS)  -o $@ 
.PHONY : clean
clean:
	rm -f $(TARGET)
