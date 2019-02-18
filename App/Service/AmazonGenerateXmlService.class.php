<?php

# 用于生成刊登所需xml
class AmazonGenerateXmlService{
    protected $documentVersion = 1.01;
    protected $xmlPath = null;
	protected $lastMessageId = null;
    public function __construct()
    {
        $this->xmlDir = APP_PATH.'Public/amazonUploadXml/' . date('Y-m-d') . '/';
    }

    /**
     * 获取上传xml
     * @param $fileNameArr 文件名
     * @param $messageType 上传类型
     * @param $merchantId 账户唯一标识
     * @return string
     */
    public function getUploadXml($fileNameArr, $messageType, $merchantId)
    {
        if (!is_array($fileNameArr)) {
            $fileNameArr = [$fileNameArr];
        }
        $xml = $this->generateXmlHeader($messageType, $merchantId);
        foreach ($fileNameArr as $fileName) {
            $xml .= file_get_contents($fileName);
        }
        $xml .= $this->generateXmlFooter();
        return $xml;
    }

    /**
     * 生成xml文件体
     * @param $data 具体数据信息
     * @param $type 文件体类型
     * @return mixed
     * @throws Exception
     */
    public function generateXmlBody($data, $type)
    {
        switch(strtolower($type)) {
            case 'product':
                return $this->generateProductXmlBody($data, $type);
            case 'price':
                return $this->generatePriceXmlBody($data, $type);
            case 'inventory':
                return $this->generateInventoryXmlBody($data, $type);
            case 'image':
                return $this->generateImageXmlBody($data, $type);
            case 'relationship':
                return $this->generateRelationshipXmlBody($data, $type);
            default:
                throw new Exception('不支持的类型');
        }
    }

    // 生成product类型xml文件
    protected function generateProductXmlBody($data, $type)
    {
        $sku = $data['Product']['SKU'];
        $xml = $this->buildXml($data);
        $fileName = $this->writeUploadXmlFile($xml, $sku, $type);
        return [$this->getLastMessageId() => $fileName];
    }


    /**
     * 生成调价xml文件
     * @param $data  数据信息
     * @param $type  类型
     * @return bool|string 成功返回文件名，失败返回false
     */
    protected function generatePriceXmlBody($data, $type)
    {
        if (empty($data['sku']) || empty($data['standardPrice']) || empty($data['currency'])) {
            return false;
        }
        $priceData['Price']['SKU'] = $data['sku'];
        $priceData['Price']['StandardPrice'] = $data['standardPrice'];
        $priceData['Price']['StandardPrice_attribute']['currency'] = $data['currency'];
		if(isset($data['sale_price'])){
			$priceData['Price']['Sale']['StartDate']=date('c',$data['sale_start']);
			$priceData['Price']['Sale']['EndDate']=date('c',$data['sale_end']);
			$priceData['Price']['Sale']['SalePrice']=$data['sale_price'];
			$priceData['Price']['Sale']['SalePrice_attribute']['currency'] = $data['currency'];
		}
        if (! empty($data['salePrice'])) {
            $priceData['Price']['Sale']['StartDate'] = $data['startDate'] . 'T00:00:00Z';
            $priceData['Price']['Sale']['EndDate'] = $data['endDate'] . 'T00:00:00Z';
            $priceData['Price']['Sale']['SalePrice'] = $data['salePrice'];
            $priceData['Price']['Sale']['SalePrice_attribute']['currency'] = $data['currency'];
        }
        $xml = $this->buildXml($priceData);
        $fileName = $this->writeUploadXmlFile($xml, $data['sku'], $type);
        return [$this->getLastMessageId() => $fileName];
    }

    // 生成Inventory类型xml文件
    protected function generateInventoryXmlBody($data, $type)
    {
        $inventoryData['Inventory']['SKU'] = $data['sku'];
        $inventoryData['Inventory']['Quantity'] = $data['inventory'];
		$inventoryData['Inventory']['FulfillmentLatency']=1;
        $xml = $this->buildXml($inventoryData);
        $fileName = $this->writeUploadXmlFile($xml, $data['sku'], $type);
        return [$this->getLastMessageId() => $fileName];
    }

    // 生成Image类型xml文件
    protected function generateImageXmlBody($data, $type)
    {
        $xml = '';
//        foreach ($data as $k => $v) {
//            $xml .= $this->buildXml($v);
//        }
        $xml .= $this->buildXml($data);
        $sku = $data['ProductImage']['SKU'];
        $fileName = $this->writeUploadXmlFile($xml, $sku, $type);
        return [$this->getLastMessageId() => $fileName];
    }

    // 生成Relationship类型xml文件
    protected function generateRelationshipXmlBody($data, $type)
    {
        $relationshipData = [];
        $relationshipData['Relationship']['ParentSKU'] = $data['parentSku'];
        foreach ($data['childSkus'] as $k => $childSku) {
            $relationshipData['Relationship'][$k]['Relation']['SKU'] = $childSku;
            $relationshipData['Relationship'][$k]['Relation']['Type'] = 'Variation';
        }
        $xml = $this->buildXml($relationshipData);
        $fileName = $this->writeUploadXmlFile($xml, $data['parentSku'], $type);
        return [$this->getLastMessageId() => $fileName];
    }

    /**
     * 获取消息id，以当前时间生成
     * @return string
     */
    protected function getMessageId()
    {
        if ($this->lastMessageId) {
            $randNum = intval(substr($this->lastMessageId, -3)) + 1;
        } else {
            $randNum = mt_rand(100,800);
        }
    	$this->lastMessageId=date("YmdHis") . $randNum;
        return $this->lastMessageId;
    }
	/**
	 * 最后生成的Message_id
	 */
	public function getLastMessageId(){
		return $this->lastMessageId;
	}

    /**
     * 获取xml头
     * @param $messageType 消息类型
     * @param $merchantId  账户唯一标识
     * @return string
     */
	protected function generateXmlHeader($messageType, $merchantId)
    {
        $xml = '<?xml version="1.0" encoding="utf-8" ?><AmazonEnvelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="amznenvelope.xsd"><Header><DocumentVersion>' . $this->documentVersion . '</DocumentVersion>';
        $xml .= '<MerchantIdentifier>'.$merchantId.'</MerchantIdentifier></Header><MessageType>' . ucfirst($messageType) . '</MessageType>';
        return $xml;
    }

    /**
     * 获取xml尾
     * @return string
     */
    protected function generateXmlFooter()
    {
        $xml = "</AmazonEnvelope>";
        return $xml;
    }

    # 上传数据生成xml
    public function buildXml($data){
        $xml = '<Message>';
        $xml .= '<MessageID>' . $this->getMessageId() . '</MessageID>';
		if($data['OperationType']){//指定OperationType
			$xml .= '<OperationType>'.$data['OperationType'].'</OperationType>';
			unset($data['OperationType']);
		}else{
			$xml .= '<OperationType>Update</OperationType>';
		}
        
        foreach([$data] as $v) {
            $xml .= $this->arrayToXml($v);
        }
        $xml .= '</Message>';
        return $xml;
    }

    # 数组转xml
    public function arrayToXml($arr, $lev = -1)
    {
        $lev++;
        $xml = '';
        foreach ($arr as $key=>$val){
            if (strrchr($key, '_') === '_attribute') {
                continue;
            }
            $attrInfo = '';
            if (isset($arr[$key . '_attribute'])) {
                $attrInfo = $this->getAttrInfo($arr[$key . '_attribute']);
            }
            if(is_array($val)){
                if(array_keys($val) === range(0, count($val) - 1)) {
                    $xml .= str_repeat("\t",$lev);
                    if(is_array($val[0])) {
                        $xml .= "<" . $key . $attrInfo . ">";
                        foreach ($val as $v) {
                            if (is_array($v)) {
                                $xml .= $this->arrayToXml($v, $lev);
                            } else {
                                $xml .= $v;
                            }
                            $xml .= PHP_EOL;
                        }
                        $xml .= "</" . $key . ">";
                    } else {
                        foreach ($val as $v) {
                            $xml .= "<" . $key . ">";
                            $xml .= $v;
                            $xml .= "</" . $key . ">";
                        }
                    }
                } else {
                    if (is_numeric($key)) {
                        $xml .= str_repeat("\t", $lev);
                        $xml .= $this->arrayToXml($val, $lev);
                        $xml .= PHP_EOL;
                    } else {
                        $xml .= str_repeat("\t", $lev);
                        $xml .= "<" . $key . $attrInfo . ">" . PHP_EOL . $this->arrayToXml($val, $lev) . str_repeat("\t", $lev) . "</" . $key . ">";
                        $xml .= PHP_EOL;
                    }
                }
            }else{
                $xml .= str_repeat("\t",$lev);
                $xml.="<".$key.$attrInfo.">".$val."</".$key.">";
                $xml.=PHP_EOL;
            }
        }
        return $xml;
    }

    # 获取刊登xml文件名
    protected function getXmlFileName($sku, $type)
    {
        while (1) {
            $randNum = mt_rand(1000, 9999);
            $fileName = $this->xmlDir . $sku . '_' . $type . $randNum . '.xml';
            if ( ! file_exists($fileName)) {
                return $fileName;
            }
        }
    }

    # 刊登xml生成文件
    public function writeUploadXmlFile($xml, $sku, $type){
        $file = $this->getXmlFileName($sku, $type);
        $index=strripos($file,'/');
        if(!file_exists($file)&&strripos($file,'/')!==false){
            $fileDir=substr($file,0,$index);
            if(!file_exists($fileDir)){
                mkdir($fileDir,0777,true);
            }
        }
        file_put_contents($file, $xml);
        if (file_exists($file)) {
            return $file;
        }
        return false;
    }

    // 获取节点属性信息
    private function getAttrInfo($attrData)
    {
        if (! is_array($attrData)) {
            $attrData = [$attrData];
        }
        $info = '';
        foreach ($attrData as $k => $v) {
            $info .= ' ' . $k . '="' . $v . '"';
        }
        return $info;
    }
}
