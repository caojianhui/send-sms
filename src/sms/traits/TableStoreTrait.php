<?php


namespace Send\Sms\Traits;


use Aliyun\OTS\Consts\ColumnReturnTypeConst;
use Aliyun\OTS\Consts\ColumnTypeConst;
use Aliyun\OTS\Consts\ComparatorTypeConst;
use Aliyun\OTS\Consts\FieldTypeConst;
use Aliyun\OTS\Consts\LogicalOperatorConst;
use Aliyun\OTS\Consts\PrimaryKeyTypeConst;
use Aliyun\OTS\Consts\QueryTypeConst;
use Aliyun\OTS\Consts\RowExistenceExpectationConst;
use Aliyun\OTS\OTSClient as OTSClient;

trait TableStoreTrait
{

    /**
     * @param string $tableName
     * @throws \Aliyun\OTS\OTSClientException
     * @throws \Aliyun\OTS\OTSServerException
     * 创建数据表
     */
    public static function createTable($tableName='sms_logs'){
        $array = config('sendsms.table_store');
        $otsClient = new OTSClient ($array);
        $request = array (
            'table_meta' => array (
                'table_name' => $tableName, // 表名为 MyTable
                'primary_key_schema' => array (
                    array('tenant_id', PrimaryKeyTypeConst::CONST_INTEGER), // 第一个主键列（又叫分片键）名称为PK0, 类型为 INTEGER
                )
            ), // 第二个主键列名称为PK1, 类型为STRING

            'reserved_throughput' => array (
                'capacity_unit' => array (
                    'read' => 0, // 预留读写吞吐量设置为：0个读CU，和0个写CU
                    'write' => 0
                )
            ),
            'table_options' => array(
                'time_to_live' => -1,   // 数据生命周期, -1表示永久，单位秒
                'max_versions' => 1,    // 最大数据版本
                'deviation_cell_version_in_sec' => 86400  // 数据有效版本偏差，单位秒
            )
        );
        $otsClient->createTable($request);
    }

    /**
     * @param string $tableName
     * @throws \Aliyun\OTS\OTSClientException
     * @throws \Aliyun\OTS\OTSServerException
     * 设置多元索引
     */
    public static function createIndex($tableName='sms_logs'){
        $array = config('sendsms.table_store');
        $otsClient = new OTSClient ($array);
        $request = array(
            'table_name' => $tableName,
            'index_name' => 'search_index',
            'schema' => array(
                'field_schemas' => array(
                    array(
                        'field_name' => 'tenant_id',
                        'field_type' => FieldTypeConst::LONG,
                        'index' => true,
                        'enable_sort_and_agg' => true,
                        'store' => true,
                        'is_array' => false
                    ),
                    array(
                        'field_name' => 'type',
                        'field_type' => FieldTypeConst::LONG,
                        'index' => true,
                        'enable_sort_and_agg' => true,
                        'store' => true,
                        'is_array' => false
                    ),
                    array(
                        'field_name' => 'temp_id',
                        'field_type' => FieldTypeConst::KEYWORD,
                        'index' => true,
                        'enable_sort_and_agg' => false,
                        'store' => true,
                        'is_array' => false
                    ),
                    array(
                        'field_name' => 'msgid',
                        'field_type' => FieldTypeConst::KEYWORD,
                        'index' => true,
                        'enable_sort_and_agg' => true,
                        'store' => true,
                        'is_array' => false
                    ),
                    array(
                        'field_name' => 'to',
                        'field_type' => FieldTypeConst::KEYWORD,
                        'index' => true,
                        'enable_sort_and_agg' => true,
                        'store' => true,
                        'is_array' => false
                    ),
                    array(
                        'field_name' => 'refund_at',
                        'field_type' => FieldTypeConst::LONG,
                        'index' => true,
                        'enable_sort_and_agg' => true,
                        'store' => true,
                        'is_array' => false
                    ),
                    array(
                        'field_name' => 'sended_at',
                        'field_type' => FieldTypeConst::LONG,
                        'index' => true,
                        'enable_sort_and_agg' => true,
                        'store' => true,
                        'is_array' => false
                    ),
                    array(
                        'field_name' => 'status',
                        'field_type' => FieldTypeConst::LONG,
                        'index' => true,
                        'enable_sort_and_agg' => true,
                        'store' => true,
                        'is_array' => false
                    ),
                    array(
                        'field_name' => 'result_status',
                        'field_type' => FieldTypeConst::LONG,
                        'index' => true,
                        'enable_sort_and_agg' => true,
                        'store' => true,
                        'is_array' => false
                    ),
                    array(
                        'field_name' => 'agents',
                        'field_type' => FieldTypeConst::KEYWORD,
                        'index' => true,
                        'enable_sort_and_agg' => true,
                        'store' => true,
                        'is_array' => false
                    ),
                    array(
                        'field_name' => 'content',
                        'field_type' => FieldTypeConst::KEYWORD,
                        'index' => true,
                        'enable_sort_and_agg' => true,
                        'store' => true,
                        'is_array' => false
                    ),
                    array(
                        'field_name' => 'params',
                        'field_type' => FieldTypeConst::KEYWORD,
                        'index' => true,
                        'enable_sort_and_agg' => true,
                        'store' => true,
                        'is_array' => false
                    ),
                    array(
                        'field_name' => 'result_info',
                        'field_type' => FieldTypeConst::KEYWORD,
                        'index' => true,
                        'enable_sort_and_agg' => true,
                        'store' => true,
                        'is_array' => false
                    ),

                ),
                'index_setting' => array(
                    'routing_fields' => array("tenant_id")
                ),
            )
        );
        $otsClient->createSearchIndex($request);
    }


    /**
     * @param $data
     * @param string $tableName
     * @return mixed
     * @throws \Aliyun\OTS\OTSClientException
     * @throws \Aliyun\OTS\OTSServerException
     * 插入数据
     */
    public function putRow($data, $tableName='sms_logs'){
        $array = config('sendsms.table_store');
        $otsClient = new OTSClient ($array);
        $request = array (
            'table_name' => $tableName,
            'condition' => RowExistenceExpectationConst::CONST_IGNORE, // condition可以为IGNORE, EXPECT_EXIST, EXPECT_NOT_EXIST
            'primary_key' => array ( // 主键
                array('tenant_id', $data['tenant_id']),
            ),
            'attribute_columns' => array( // 属性
                array('to',$data['to']??''),
                array('type',$data['type']??1),
                array('temp_id',$data['temp_id']??''),
                array('msgid',$data['msgid']??''),
                array('content',$data['content']??''),
                array('status',$data['status']?:1),
                array('refund_at',$data['refund_at']??0),
                array('result_status',$data['result_status']??''),
                array('agents',$data['agents']??''),
                array('params',$data['params']??''),
                array('result_info',$data['result_info']??''),
                array('sended_at',$data['created_at']?strtotime($data['created_at']):0),
            )
        );
       return  $otsClient->putRow ($request);
    }

    /**
     * @param array $info
     * @param string $tableName
     * @return mixed
     * @throws \Aliyun\OTS\OTSClientException
     * @throws \Aliyun\OTS\OTSServerException
     * 更新数据
     */
    public static function updateRows(array $info, $where,$tableName='sms_logs'){
        $otsClient = self::getClient();
        foreach($info as $key =>$item){
            if($key!='tenant_id'){
                $query[] =[$key,$item];
            }
        }
        $request = array (
            'table_name' => $tableName,
            'condition' => [
                'row_existence' => RowExistenceExpectationConst::CONST_EXPECT_EXIST,
                'column_condition' => [
                    'logical_operator' =>LogicalOperatorConst::CONST_AND,
                    'sub_conditions' => [
                        [
                            'column_name' => 'agents',
                            'value' =>$where['agents'] ,
                            'comparator' => ComparatorTypeConst::CONST_EQUAL
                        ],
                        [
                            'column_name' => 'msgid',
                            'value' => $where['msgid'],
                            'comparator' => ComparatorTypeConst::CONST_EQUAL
                        ]
                    ]
                ]
            ],
            'primary_key' => array ( // 主键
                array('tenant_id', $info['tenant_id']),
            ),
            'update_of_attribute_columns'=> array(
                'PUT' => $query,
            )
        );
        return $otsClient->updateRow ($request);
    }


    /**
     * @param $query
     * @return array
     * @throws \Aliyun\OTS\OTSClientException
     * @throws \Aliyun\OTS\OTSServerException
     */
    public static function getTotal($query){
        $query = self::getQuery($query);
        if(empty($query)) {
            return [];
        }
        $otsClient = self::getClient();
        $request = self::getRequest($query,2);
        $response = $otsClient->search($request);
        return $response['total_hits'];
    }

    /**
     * @return OTSClient
     * 获取tablestore配置信息
     */
    private static function getClient()
    {
        $array = config('admin.table_store');
        return new OTSClient($array);
    }


    /**
     * @param $where
     * @return array
     */
    private static function getQuery($where)
    {
        foreach($where as $key => $value){
            if ($key=='startTime'){
                $arr = array(
                    'query_type' => QueryTypeConst::RANGE_QUERY,
                    'query' => array(
                        'field_name' => 'sended_at',
                        'range_from' => $value[0],
                        'include_lower' => true,
                        'range_to' => $value[1],
                        'include_upper' => false
                    ));
            }else{
                $arr =  array(
                    'query_type' => QueryTypeConst::TERM_QUERY,
                    'query' => array(
                        'field_name' => $key,
                        'term' => $value
                    ));
            }
            $query[] = $arr;
        }
        return $query;
    }


    /**
     * @param $query
     * @param $limit
     * @param string $tableName
     * @return array
     * 设置request
     */
    private static function getRequest($query, $limit, $tableName='sms_logs')
    {
        return array(
            'table_name' => $tableName,
            'index_name' => 'search_index',
            'search_query' => array(
                'offset' => 0,
                'limit' => $limit,
                'get_total_count' => true,
                'query' => array(
                    'query_type' => QueryTypeConst::BOOL_QUERY,
                    'query' => array('must_queries' => $query,
                    )
                ),
            ),
            'columns_to_get' => array('return_type' => ColumnReturnTypeConst::RETURN_ALL,
            ));
    }


    /**
     * @param $where
     * @param $limit
     * @param bool $page
     * @return array|\Illuminate\Support\Collection|mixed
     * @throws \Aliyun\OTS\OTSClientException
     * @throws \Aliyun\OTS\OTSServerException
     * 获取列表
     */
    public  static function getList($where, $limit, $page = true)
    {
        $query = self::getQuery($where);
        if(empty($query)) {
            return [];
        }
        $otsClient = self::getClient();
        $request = self::getRequest($query,$limit);
        $response = $otsClient->search($request);
        return self::getData($response, $otsClient, $request,$page);
    }

    /**
     * @param $response
     * @param $otsClient
     * @param $request
     * @param bool $page
     * @return \Illuminate\Support\Collection|mixed
     *分页查询数据
     */
    private static function getData($response, $otsClient, $request, $page=true)
    {
        $lists = collect([]);
        $lists = self::setLists($response, $lists);
        if($page){
            while($response['next_token']!=null){
                $request['search_query']['token'] = $response['next_token'];
                $request['search_query']['sort'] = null;//有next_token时，不设置sort，token中含sort信息
                $response = $otsClient->search($request);
                $lists = self::setLists($response, $lists);
            }
        }
        return $lists;
    }

    /**
     * @param $response
     * @param $lists
     * @return mixed
     * 返回列表数据
     */
    private static function setLists($response, $lists)
    {
        collect($response['rows'])->each(function($item) use (&$lists){
            $arr = collect();
            if(isset($item['primary_key'])){
                foreach($item['primary_key'] as $value){
                    $arr->put($value[0], (int)$value[1]);
                }
            }
            if(isset($item['attribute_columns'])){
                foreach($item['attribute_columns'] as $column){
                    $arr->put($column[0], $column[1]);
                }
            }
            $lists->push($arr);
        });
        return $lists;
    }

}
