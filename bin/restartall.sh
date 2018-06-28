#/bin/bash

FSOF_BIN=`pwd`;
DIRECTORY_SEPARATOR="/";
FILE_NAME="servicename_master_process";

#记录启动的服务
ps -ef| grep master_process| grep -v grep > $FSOF_BIN$DIRECTORY_SEPARATOR$FILE_NAME;

#启动服务进程
while read LINE
do
	SER_NAME=`echo $LINE| awk '{print $NF}'|sed 's/_master_process//g'`;
	echo "********************Beganing Start" $SER_NAME;
	php $FSOF_BIN$DIRECTORY_SEPARATOR"app_admin.php" $SER_NAME restart;
	echo "********************Ending Start" $SER_NAME;
done < $FSOF_BIN$DIRECTORY_SEPARATOR$FILE_NAME
