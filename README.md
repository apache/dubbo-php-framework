# dubbo-php-framework
dubbo-php-framework是一个用于php语言的RPC通讯框架，与dubbo在协议上完全兼容，可同时作为provider端和consumer端,使用zookeeper用于服务注册发现,序列化使用fastjson.

![image](https://github.com/lexin-fintech/dubbo-php-framework/blob/master/arch.png)

## 说明
* php provider以多进程方式运行，其中worker进程用于处理具体业务，manager进程控制worker进程的生命周期，master进程处理网络IO.
* agent监听registry中provider地址信息的变更并同步到本地redis，供本机上所有php consumer共享.
* php consumer、redis、agent三者部署在所有consumer机器上，并且彼此以unix socket通信.
* provider_admin部署在所有provider机器上，用于控制该机器上所有php provider的生命周期.


[Quick start](https://github.com/lexin-fintech/dubbo-php-framework/wiki/Quick-Start)
