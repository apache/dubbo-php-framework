## <font size=6>dubbo-php-framework</font>


<font size=3 face="Segoe UI">dubbo-php-framework is a RPC communication framework for PHP language. It is fully compatible with Dubbo protocol, and can be used as provider terminal and consumer terminal simultaneously. Using zookeeper for service registration discovery, and using fastjson for Serialization.</font>

![image](https://github.com/lexin-fintech/dubbo-php-framework/blob/master/arch.png)

## <font size=5>Introduction</font>
- <font size=3 face="Segoe UI">php provider runs in multiple processes. The worker process is used to process specific business, the manager process controls the lifecycle of the worker process, and the master process processes the network IO.</font>
- <font size=3 face="Segoe UI">Agent monitors the change of provider address information in registry and synchronizes them to local redis for all php consumers on the machine to share.</font>
- <font size=3 face="Segoe UI">php consumer、redis and agent are deployed on all consumer machines and communicate with each other on unix socket.</font>
- <font size=3 face="Segoe UI">provider_admin is deployed on all provider machines to control the lifecycle of all php providers on that machine.</font>

<a href="http://www.w3school.com.cn">Quick start</a>


