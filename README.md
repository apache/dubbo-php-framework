English | [中文](./README-CN.md)

# dubbo-php-framework

dubbo-php-framework is a RPC communication framework for PHP language. It is fully compatible with Dubbo protocol, and can be used as provider terminal and consumer terminal simultaneously. Using zookeeper for service registration discovery, and using fastjson and hessian2 for Serialization

![arch](https://github.com/crazyxman/dubbo-php-framework/blob/master/Arch.png)

# Introduction
- php provider runs in multiple processes. The worker process is used to process specific business, the manager process controls the lifecycle of the worker process, and the master process processes the network IO.
- Agent monitors the change of provider address information in registry and synchronizes them to local memory for all php consumers on the machine to share
- consumer、 agent are deployed on all consumer machines and communicate with each other on unix socket or TCP socket
provider is deployed on all provider machines to control the lifecycle of all php providers on that machine

# Changelog
- Rewrite the entire code, have better readability, and expand (help more people join in)
- Introduce composer for management loading, which is beneficial for installation and use as a component of other frameworks.
- The original agent module was changed from c + redis to php to reduce component dependencies.
- Provider, consumer, agent and other configuration files are independent of each other, and the storage location is customized.
- Both provider and consumer support serialization of hessian2 data.
- Configuration file format changed from ini to yaml, reducing redundant fields and improving readability.
- Remove log4php log component, provide external log component implementation interface for custom implementation.
- The provider module introduces annotations to register existing code as a dubbo service without modification, without intrusion.
- The swoole_server configuration and callback function can be customized by the user, which is helpful for users to optimize the service according to the current application scenario.
- The TCP connection is maintained while consuming the same ip: port provider.
- The returned hessian serialized data is transformed from a complex object into an array after parsing.
- The data collected by monitor is more complete.


Wiki: [中文](https://github.com/crazyxman/dubbo-php-framework/wiki/%E4%B8%AD%E6%96%87)

