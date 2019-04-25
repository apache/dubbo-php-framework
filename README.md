## <font size=6>php framework for apache dubbo</font>


<font size=3 face="Segoe UI">php-for-apache-dubbo is a RPC communication framework for PHP language. It is fully compatible with Dubbo protocol, and can be used as provider terminal and consumer terminal simultaneously. Using zookeeper for service registration discovery, and using fastjson for Serialization.</font>

![image](https://github.com/lexin-fintech/dubbo-php-framework/blob/master/arch.png)

## <font size=5>Introduction</font>
- <font size=3 face="Segoe UI">php provider runs in multiple processes. The worker process is used to process specific business, the manager process controls the lifecycle of the worker process, and the master process processes the network IO.</font>
- <font size=3 face="Segoe UI">Agent monitors the change of provider address information in registry and synchronizes them to local redis for all php consumers on the machine to share.</font>
- <font size=3 face="Segoe UI">php consumer、redis and agent are deployed on all consumer machines and communicate with each other on unix socket.</font>
- <font size=3 face="Segoe UI">provider_admin is deployed on all provider machines to control the lifecycle of all php providers on that machine.</font>

<a href="https://github.com/dubbo/php-framework-for-apache-dubbo/wiki/%E5%BF%AB%E9%80%9F%E5%BC%80%E5%A7%8B">Quick start (Chinese)</a>
<a href="https://github.com/dubbo/php-framework-for-apache-dubbo/wiki/%E5%AE%89%E8%A3%85%E5%90%91%E5%AF%BC">Installation guide (Chinese)</a>
<a href="https://github.com/dubbo/php-framework-for-apache-dubbo/wiki/%E9%85%8D%E7%BD%AE%E5%8F%82%E8%80%83%E6%89%8B%E5%86%8C">Configuration guide (Chinese)</a>


