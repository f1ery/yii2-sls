<?php
/**
 * SLS日志helper
 * @copyright（c）2020 上海七猫文化传媒有限公司
 * @author: wulve.wang <wangwulue@qimao.com>
 * Time: 下午2:07
 */

namespace f1ery\sls;

use Yii;

class AliyunSlsLog
{
    //日志服务的域名
    private $endpoint;
    //阿里云访问密钥AccessKey ID
    private $accessKeyId;
    //阿里云访问密钥AccessKey Secret
    private $accessKey;
    //token
    private $token = '';

    //日志project
    private $project;

    private $client;

    /**
     * AliyunSlsLog constructor.
     * @param string $accessKeyId
     * @param string $accessKey
     * @param string $endpoint
     * @param string $project
     */
    public function __construct(string $accessKeyId, string $accessKey, string $endpoint, string $project)
    {
        $this->accessKeyId = $accessKeyId;
        $this->accessKey = $accessKey;
        $this->endpoint = $endpoint;
        $this->project = $project;
        require_once(__DIR__ . '/Log_Autoload.php');
        $this->client = new \Aliyun_Log_Client($this->endpoint, $this->accessKeyId, $this->accessKey, $this->token);
    }

    /**
     * 创建logstore
     *
     * @param string $logStore
     * @param int|int $ttl
     * @param int|int $shardCount
     * @return \Aliyun_Log_Models_CreateLogstoreResponse
     * @author wulve.wang<wangwulue@qimao.com>
     * @date 2021/6/7 上午11:37:09
     */
    public function createLogStore(string $logStore, int $ttl = 3650, int $shardCount = 2)
    {
        try {
            //配置Project名称、Logstore名称、数据保存时长和Shard数量。其中如果数据保存时长配置为3650，表示永久保存
            $req2 = new \Aliyun_Log_Models_CreateLogstoreRequest($this->project, $logStore, $ttl, $shardCount);
            return $this->client->createLogstore($req2);
        } catch (\Exception $e) {
            Yii::error($e->getMessage());
        }
    }

    /**
     * 写入sls日志
     *
     * @param string $project
     * @param string $logStore
     * @param string $topic
     * @param array $contents ['mes' => '测试信息']
     * @param string|null $source
     * @return \Aliyun_Log_Models_PutLogsResponse|string
     * @author wulve.wang<wangwulue@qimao.com>
     * @date 2021/6/2 下午5:05:16
     */
    public function putLog(string $logStore, string $topic, array $contents, string $source = null)
    {
        $contents = $this->addBaseContents($contents);
        //写入日志
        $logItem = new \Aliyun_Log_Models_LogItem();
        $logItem->setTime(time());
        $logItem->setContents($contents);
        $logitems = [$logItem];
        $request = new \Aliyun_Log_Models_PutLogsRequest($this->project, $logStore, $topic, $source, $logitems);
        try {
            return $this->client->putLogs($request);
        } catch (\Aliyun_Log_Exception $ex) {
            Yii::error($ex->getErrorMessage());
        } catch (\Exception $ex) {
            Yii::error($ex->getMessage());
        }
        return '';
    }


    /**
     * 添加基础日志内容
     *
     * @param array $contents
     * @return array
     * @author wulve.wang<wangwulue@qimao.com>
     * @date 2021/6/3 上午11:14:30
     */
    private function addBaseContents(array $contents): array
    {
        $traceMes = '';
        $traces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        //取前两个trace信息记录到日志
        $handleTraces = array_slice($traces, 0, 2);
        foreach ($handleTraces as $trace) {
            if (isset($trace['file'], $trace['line']) && strpos($trace['file'], YII2_PATH) === false) {
                $traceMes .= "(#{$trace['file']}#:{$trace['line']})" . PHP_EOL;
            }
        }

        if (in_array(php_sapi_name(), ['cli', 'cli_server'])) {
            //命令行
            $baseContents = [
                'client' => isset($_SERVER['SSH_CLIENT']) ? json_encode($_SERVER['SSH_CLIENT']) : '',//客户端链接ip，端口
                'user' => $_SERVER['USER'] ?? '',//执行脚本的用户名
                'argv' => isset($_SERVER['argv']) ? json_encode($_SERVER['argv']) : '',//脚本参数
            ];
        } else {
            //web请求
            $baseContents = [
                //'server_port' => $_SERVER['SERVER_PORT'] ?? '',
                'host' => $_SERVER['HTTP_HOST'] ?? '',
                'query' => $_SERVER['REQUEST_URI'] ?? '',
                'reffer' => $_SERVER['HTTP_REFERER'] ?? '',
                'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            ];
        }
        $baseContents['params'] = json_encode($_REQUEST);
        $baseContents['trace'] = $traceMes;
        return array_merge($contents, $baseContents);
    }

    /**
     * 列出logstore列表
     *
     * @param string $project
     * @return \Aliyun_Log_Models_ListLogstoresResponse
     * @author wulve.wang<wangwulue@qimao.com>
     * @date 2021/6/7 上午11:43:39
     */
    function listLogstores()
    {
        try {
            $request = new \Aliyun_Log_Models_ListLogstoresRequest($this->project);
            return $this->client->listLogstores($request);
        } catch (\Aliyun_Log_Exception $ex) {
            Yii::error($ex->getErrorMessage());
        } catch (\Exception $ex) {
            Yii::error($ex->getMessage());
        }
    }

    /**
     * 列出topic列表
     *
     * @param string $logstore
     * @return \Aliyun_Log_Models_ListTopicsResponse
     * @author wulve.wang<wangwulue@qimao.com>
     * @date 2021/6/7 上午11:45:04
     */
    function listTopics(string $logstore)
    {
        $request = new \Aliyun_Log_Models_ListTopicsRequest($this->project, $logstore);
        try {
            return $this->client->listTopics($request);
        } catch (\Aliyun_Log_Exception $ex) {
            Yii::error($ex->getErrorMessage());
        } catch (\Exception $ex) {
            Yii::error($ex->getMessage());
        }
    }

    /**
     * 获取某个topic下某个时间间隔内的所有logs
     *
     * @param string $logstore
     * @param string $topic
     * @param int $time
     * @author wulve.wang<wangwulue@qimao.com>
     * @date 2021/6/7 上午11:53:42
     */
    function getLogs(string $logstore, string $topic, int $time)
    {
        $from = time() - $time;
        $to = time();
        $request = new \Aliyun_Log_Models_GetLogsRequest($this->project, $logstore, $from, $to, $topic, '', 100, 0, False);
        try {
            $response = $this->client->getLogs($request);
            foreach ($response->getLogs() as $log) {
                print $log->getTime() . "\t";
                foreach ($log->getContents() as $key => $value) {
                    print $key . ":" . $value . "\t";
                }
                print "\n";
            }

        } catch (\Aliyun_Log_Exception $ex) {
            Yii::error($ex->getErrorMessage());
        } catch (\Exception $ex) {
            Yii::error($ex->getMessage());
        }
    }

    /**
     * 获取某个topic下histograms列表
     *
     * @param string $logstore
     * @param string $topic
     * @param int $time
     * @return \Aliyun_Log_Models_GetHistogramsResponse
     * @author wulve.wang<wangwulue@qimao.com>
     * @date 2021/6/7 下午1:31:13
     */
    function getHistograms(string $logstore, string $topic, int $time)
    {
        $from = time() - $time;
        $to = time();
        $request = new \Aliyun_Log_Models_GetHistogramsRequest($this->project, $logstore, $from, $to, $topic, '');
        try {
            return $this->client->getHistograms($request);
        } catch (\Aliyun_Log_Exception $ex) {
            Yii::error($ex->getErrorMessage());
        } catch (\Exception $ex) {
            Yii::error($ex->getMessage());
        }
    }

    /**
     * 获取logstore下的所有shard列表
     *
     * @param string $logstore
     * @author wulve.wang<wangwulue@qimao.com>
     * @date 2021/6/7 下午1:32:32
     */
    function listShard(string $logstore)
    {
        $request = new \Aliyun_Log_Models_ListShardsRequest($this->project, $logstore);
        try {
            return $this->client->listShards($request);
        } catch (\Aliyun_Log_Exception $ex) {
            Yii::error($ex->getErrorMessage());
        } catch (\Exception $ex) {
            Yii::error($ex->getMessage());
        }
    }

    /**
     * 获取某个logstore下logs(100条)
     *
     * @param string $logstore
     * @throws \Aliyun_Log_Exception
     * @author wulve.wang<wangwulue@qimao.com>
     * @date 2021/6/7 下午1:38:40
     */
    function batchGetLogs(string $logstore)
    {
        $listShardRequest = new \Aliyun_Log_Models_ListShardsRequest($this->project, $logstore);
        $listShardResponse = $this->client->listShards($listShardRequest);
        foreach ($listShardResponse->getShardIds() as $shardId) {
            $getCursorRequest = new \Aliyun_Log_Models_GetCursorRequest($this->project, $logstore, $shardId, null, time() - 60);
            $response = $this->client->getCursor($getCursorRequest);
            $cursor = $response->getCursor();
            $count = 100;
            while (true) {
                $batchGetDataRequest = new \Aliyun_Log_Models_BatchGetLogsRequest($this->project, $logstore, $shardId, $count, $cursor);
                logVarDump($batchGetDataRequest);
                $response = $this->client->batchGetLogs($batchGetDataRequest);
                if ($cursor == $response->getNextCursor()) {
                    break;
                }
                $logGroupList = $response->getLogGroupList();
                foreach ($logGroupList as $logGroup) {
                    print ($logGroup->getCategory());
                    foreach ($logGroup->getLogsArray() as $log) {
                        foreach ($log->getContentsArray() as $content) {
                            print($content->getKey() . ":" . $content->getValue() . "\t");
                        }
                        print("\n");
                    }
                }
                $cursor = $response->getNextCursor();
            }
        }
    }

    /**
     * 获取60秒内logs
     *
     * @param string $logstore
     * @throws \Aliyun_Log_Exception
     * @author wulve.wang<wangwulue@qimao.com>
     * @date 2021/6/7 下午1:43:01
     */
    function batchGetLogsWithRange(string $logstore)
    {
        $listShardRequest = new \Aliyun_Log_Models_ListShardsRequest($this->project, $logstore);
        $listShardResponse = $this->client->listShards($listShardRequest);
        foreach ($listShardResponse->getShardIds() as $shardId) {
            //pull data which reached server at time range [now - 60s, now) for every shard
            $curTime = time();
            $beginCursorResponse = $this->client->getCursor(new \Aliyun_Log_Models_GetCursorRequest($this->project, $logstore, $shardId, null, $curTime - 60));
            $beginCursor = $beginCursorResponse->getCursor();
            $endCursorResponse = $this->client->getCursor(new \Aliyun_Log_Models_GetCursorRequest($this->project, $logstore, $shardId, null, $curTime));
            $endCursor = $endCursorResponse->getCursor();
            $cursor = $beginCursor;
            print("-----------------------------------------\nbatchGetLogs for shard: " . $shardId . ", cursor range: [" . $beginCursor . ", " . $endCursor . ")\n");
            $count = 100;
            while (true) {
                $batchGetDataRequest = new \Aliyun_Log_Models_BatchGetLogsRequest($this->project, $logstore, $shardId, $count, $cursor, $endCursor);
                $response = $this->client->batchGetLogs($batchGetDataRequest);
                $logGroupList = $response->getLogGroupList();
                $logGroupCount = 0;
                $logCount = 0;
                foreach ($logGroupList as $logGroup) {
                    $logGroupCount += 1;
                    foreach ($logGroup->getLogsArray() as $log) {
                        $logCount += 1;
                        foreach ($log->getContentsArray() as $content) {
                            print($content->getKey() . ":" . $content->getValue() . "\t");
                        }
                        print("\n");
                    }
                }
                $nextCursor = $response->getNextCursor();
                print("batchGetLogs once, cursor: " . $cursor . ", nextCursor: " . nextCursor . ", logGroups: " . $logGroupCount . ", logs: " . $logCount . "\n");
                if ($cursor == $nextCursor) {
                    //read data finished
                    break;
                }
                $cursor = $nextCursor;
            }
        }
    }

    /**
     * 合并shard
     *
     * @param string $logstore
     * @param string $shardId
     * @author wulve.wang<wangwulue@qimao.com>
     * @date 2021/6/7 下午1:44:24
     */
    function mergeShard(string $logstore, string $shardId)
    {
        $request = new \Aliyun_Log_Models_MergeShardsRequest($this->project, $logstore, $shardId);
        try {
            return $this->client->mergeShards($request);
        } catch (\Aliyun_Log_Exception $ex) {
            Yii::error($ex->getErrorMessage());
        } catch (\Exception $ex) {
            Yii::error($ex->getMessage());
        }
    }

    /**
     * 切割shard
     *
     * @param string $logstore
     * @param string $shardId
     * @param string $midHash
     * @return \Aliyun_Log_Models_ListShardsResponse
     * @author wulve.wang<wangwulue@qimao.com>
     * @date 2021/6/7 下午1:46:00
     */
    function splitShard(string $logstore, string $shardId, string $midHash)
    {
        $request = new \Aliyun_Log_Models_SplitShardRequest($this->project, $logstore, $shardId, $midHash);
        try {
            return $this->client->splitShard($request);
        } catch (\Aliyun_Log_Exception $ex) {
            Yii::error($ex->getErrorMessage());
        } catch (\Exception $ex) {
            Yii::error($ex->getMessage());
        }
    }

}
