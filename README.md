# Yii2的sls组件


## API版本
1.0.3


## 组件发布日期
2021-06-09


## 简单介绍
AliyunSlsLog是一个php的composer包，通过putLog方法写入数据到sls的log中，也可以通过getLogs方法获取logs列表。

API Reference: (https://help.aliyun.com/document_detail/29007.html)


## 安装
方法一：通过composer包的安装方法

composer require --prefer-dist qimao/yii2-sls


方法二：添加composer.json配置文件

"qimao/yii2-sls": "~1.0.0"

## 使用说明
    $endpoint = 'cn-shanghai.log.aliyuncs.com';
    $accessKeyId = 'xxxxxxxxxxxxxx';
    $accessKey = 'xxxxxxxxxxxxxx';
    $project = 'xxxxxxxx';
    $logstore = 'xxxxxxx';
    $topic = 'xxxxxx';
    $contents = [
        'mes' => 'test mes',
    ];
    
    //创建logstore
    (new AliyunSlsLog($accessKeyId, $accessKey, $endpoint, $project))->createLogStore($logstore);
    
    //推送log
    (new AliyunSlsLog($accessKeyId, $accessKey, $endpoint, $project))->putLog($logstore, $topic, $contents);
    
    //批量获取一天内(86400s)某个logstore下logs(前100条)
    $rs = (new AliyunSlsLog($accessKeyId, $accessKey, $endpoint, $project))->batchGetLogs($logstore);
    
    //列出某个project下logstore列表
    $rs = (new AliyunSlsLog($accessKeyId, $accessKey, $endpoint, $project))->listLogstores();
    
    //列出某个project下topic列表
    $rs = (new AliyunSlsLog($accessKeyId, $accessKey, $endpoint, $project))->listTopics($logStore);
    
    //获取一天内logs
    $rs = (new AliyunSlsLog($accessKeyId, $accessKey, $endpoint, $project))->getLogs($logstore, $topic, 86400);
    
    //获取Histograms
    $rs = (new AliyunSlsLog($accessKeyId, $accessKey, $endpoint, $project))->getHistograms($logstore, $topic, 86400);
    
    //获取shard列表
    $rs = (new AliyunSlsLog($accessKeyId, $accessKey, $endpoint, $project))->listShard($logstore);
    
    //获取60秒内logs
    $rs = (new AliyunSlsLog($accessKeyId, $accessKey, $endpoint, $project))->batchGetLogsWithRange($logstore);
    
    //合并shard
    $rs = (new AliyunSlsLog($accessKeyId, $accessKey, $endpoint, $project))->mergeShard($logstore, 'MTYyaaaaaaaaaaaaaa=');
    
    //切割shard
    $rs = (new AliyunSlsLog($accessKeyId, $accessKey, $endpoint, $project))->splitShard($logstore, 'MTYyaaaaaaaaaaaaaa=', '');
    print_r($rs);
    
## 异常处理
查看对应项目error目录日志(Yii::error())

## Environment Requirement
1. PHP 7+
2. Yii2
