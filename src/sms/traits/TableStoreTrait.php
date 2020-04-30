<?php


namespace Send\Sms\Traits;


use Aliyun\OTS\Consts\ColumnReturnTypeConst;
use Aliyun\OTS\Consts\ColumnTypeConst;
use Aliyun\OTS\Consts\ComparatorTypeConst;
use Aliyun\OTS\Consts\FieldTypeConst;
use Aliyun\OTS\Consts\LogicalOperatorConst;
use Aliyun\OTS\Consts\PrimaryKeyOptionConst;
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
                    array('id',PrimaryKeyTypeConst::CONST_INTEGER, PrimaryKeyOptionConst::CONST_PK_AUTO_INCR)
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
                        'field_name' => 'id',
                        'field_type' => FieldTypeConst::LONG,
                        'index' => true,
                        'enable_sort_and_agg' => true,
                        'store' => true,
                        'is_array' => false
                    ),
                    array(
                        'field_name' => 'act_id',
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
                        'field_type' => FieldTypeConst::KEYWORD,
                        'index' => true,
                        'enable_sort_and_agg' => true,
                        'store' => true,
                        'is_array' => false
                    ),
                    array(
                        'field_name' => 'is_back',
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
                array('id',PrimaryKeyTypeConst::CONST_INTEGER, PrimaryKeyOptionConst::CONST_PK_AUTO_INCR)

            ),
            'attribute_columns' => array( // 属性
                array('to',$data['to']??''),
                array('type',$data['type']??1),
                array('temp_id',$data['temp_id']??''),
                array('msgid',(string)$data['msgid']??''),
                array('content',$data['content']??''),
                array('status',$data['status']?:1),
                array('refund_at',$data['refund_at']??0),
                array('result_status',$data['result_status']??''),
                array('act_id',$data['act_id']??0),
                array('is_back',isset($data['result_status'])&&!empty($data['result_status'])?1:0),
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
    public static function updateRows(array $info, array $where,$tableName='sms_logs'){
        if (isEmpty($where) || isEmpty($info)) return [];
        $query = self::setUpdateData($info);
        $where = self::setUpdateWhere($where);
        $otsClient = self::getClient();
        $request = array (
            'table_name' => $tableName,
            'condition' => [
                'row_existence' => RowExistenceExpectationConst::CONST_EXPECT_EXIST,
                'column_condition' => [
                    'logical_operator' =>LogicalOperatorConst::CONST_AND,
                    'sub_conditions' => $where
                ]
            ],
            'primary_key' => array ( // 主键
                array('tenant_id', $info['tenant_id']),
                array('id', $info['id']),
            ),
            'update_of_attribute_columns'=> array(
                'PUT' => $query,
            )
        );
        $response = $otsClient->updateRow ($request);
        return $response;
    }

    /**
     * @param $info
     * @return array
     */
    private static function setUpdateData($info){
        $data = array_except($info,['id','tenant_id']);
        foreach($data as $key =>$item){
            $query[] =[$key,$item];
        }
        return $query;
    }

    /**
     * @param $where
     * @return array
     */
    private static function setUpdateWhere($where){
        $conditions = [];
        foreach ($where as $key => $item){
            $conditions[] = [
                'column_name' => $key,
                'value' =>$item ,
                'comparator' => ComparatorTypeConst::CONST_EQUAL
            ];
        }
        return $conditions;
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
        $array = config('sendsms.table_store');
        return new OTSClient($array);
    }


    /**
     * @param $where
     * @return array
     */
    private static function getQuery($where)
    {
        $query = collect([]);
        foreach($where as $key => $value){
            if ($key=='range'){
                if(is_array($value)){
                    foreach($value as $k =>$v){
                        $query->push(self::getRangeQuery($k,$v));
                    }
                }
            }elseif($key=='wildcard'){
                if(is_array($value)){
                    foreach($value as $k => $v){
                        $query->push(self::getWildcardQuery($k, $v));
                    }
                }
            }else{
                $arr =  self::getTermQuery($key, $value);
                $query->push($arr);
            }
        }
        return $query->toArray();
    }

    /**
     * @param $key
     * @param $value
     * @return array
     * 精确查询
     */
    private static function getTermQuery($key, $value){
        return array(
            'query_type' => QueryTypeConst::TERM_QUERY,
            'query' => array(
                'field_name' => $key,
                'term' => $value
            ));
    }

    /**
     * @param $key
     * @param $value
     * @return array
     * 通配符查询（模糊查询）
     */
    private static function getWildcardQuery($key, $value){
        return array(
            'query_type' => QueryTypeConst::WILDCARD_QUERY,
            'query' => array(
                'field_name' => $key,
                'value'=>$value
            ));

    }

    /**
     * @param $key
     * @param $value
     * @return array
     * 范围查询
     */
    private static function getRangeQuery($key, $value){

        if(is_array($value)){
            return array(
                'query_type' => QueryTypeConst::RANGE_QUERY,
                'query' => array(
                    'field_name' => $key,
                    'range_from' => $value[0],
                    'include_lower' => true,
                    'range_to' => $value[1],
                    'include_upper' => false
                ));
        }else{
            return array(
                'query_type' => QueryTypeConst::RANGE_QUERY,
                'query' => array(
                    'field_name' => $key,
                    'range_from' => $value,
                    'include_lower' => true,
                    'include_upper' => false
                ));
        }

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
        return self::getData($response, $request,$page);
    }

    /**
     * @param $where
     * @return array|mixed
     * @throws \Aliyun\OTS\OTSClientException
     * @throws \Aliyun\OTS\OTSServerException
     * 获取一条数据
     */
    public static function getRows($where){
        $lists = self::getList($where,1,false);
        return $lists->isNotEmpty()?$lists->first():[];
    }

    /**
     * @param $response
     * @param $request
     * @param bool $page
     * @return \Illuminate\Support\Collection|mixed
     *分页查询数据
     */
    private static function getData($response, $request, $page=true)
    {
        $lists = collect([]);
        $lists = self::setLists($response, $lists);
        if($page){
            while($response['next_token']!=null){
                $otsClient = self::getClient();
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


    /**
     * @param $tenantId
     * @param array $ids
     * @param string $tableName
     * @throws \Aliyun\OTS\OTSClientException
     * @throws \Aliyun\OTS\OTSServerException
     * 批量删除数据
     */
    public static function batchDeleteRows($tenantId, array $ids, $tableName='sms_logs'){
        $otsClient = self::getClient();
        foreach($ids as $item){
            $request = array (
                'table_name' => $tableName,
                'condition' => RowExistenceExpectationConst::CONST_IGNORE,
                'primary_key' => array ( // 主键
                    array('tenant_id', $tenantId),
                    array('id', (int)$item)
                )
            );
            $otsClient->deleteRow ($request);
        }
    }


    /**
     * @param $where
     * @param $limit
     * @param null $nextToken
     * @return array|\Illuminate\Support\Collection
     * @throws \Aliyun\OTS\OTSClientException
     * @throws \Aliyun\OTS\OTSServerException
     */
    public static function getPageList($where, $limit, $nextToken=null)
    {
        $query = self::getQuery($where);
        if(empty($query)) {
            return [];
        }
        $request = self::getRequest($query,$limit);
        if(!is_null($nextToken)){
            $request['search_query']['token'] = $nextToken;
            $request['search_query']['sort'] = null;//有next_token时，不设置sort，token中含sort信息
            $otsClient = self::getClient();
            $response = $otsClient->search($request);
        }else{
            $otsClient = self::getClient();
            $response = $otsClient->search($request);
        }
        return self::setPageLists($response);
    }


    /**
     * @param $response
     * @return \Illuminate\Support\Collection
     */
    private static function setPageLists($response)
    {
        $data = collect([]);
        $lists = collect([]);
        if(empty($response['rows'])) return collect(['data'=>$data,'next_token'=>null]);
        collect($response['rows'])->each(function($item) use (&$data){
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
            $data->push($arr);
        });
        $lists->put('data',$data);
        $lists->put('next_token',$response['next_token']??null);
        return $lists;
    }

}
