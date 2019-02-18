<?php


class AmazonXsdFileParseAction extends Action {

    private $pid = null;
    private $cateId = null;
    private $tmpCateData = [];
    private $flagNum = 1;
    private $categoryType = null;

    # 基本信息入库
    public function parseBaseFile()
    {
        $amazonBaseNodeTypeModel = new AmazonBaseNodeTypeModel();
        $dir = 'C:\Users\Administrator.USER-20170118OD\Desktop\am\newXsd1/';
        libxml_disable_entity_loader(true);
        $fileName = 'amzn-base';

        $file                       = $dir . $fileName . '.xsd';
        $xml                        = file_get_contents($file);
        if(empty($xml)) {
            echo $file . '文件找不到' . '<br/>';
            die;
        }
        $xml = preg_replace('/^.*?(?=<xsd:element)/ms', '<document>', $xml, 1);
        $xml = preg_replace('/<\/xsd:schema>/ms', '</document>', $xml, 1);
        $obj                        = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $allData                       = json_decode(json_encode($obj), true);
        $data = $allData['xsd:element'];
        if(empty($data)) {
            echo $file . '数据为空' . '<br/>';
        }
        echo '<br/>' . $file . '文件开始解析分类' . '<br/>';
        $saveData = array();
        $this->pid = 0;
        $data = $this->dealDataElement($data);
        $this->dealType($data,0,$amazonBaseNodeTypeModel);

        # 处理element外的其他信息
        if($allData['xsd:simpleType']) {
            $simptyData = $this->dealDataElement($allData['xsd:simpleType']);
            foreach($simptyData as $simptyInfo) {
                $resSimptyData = $this->dealSimpleType($simptyInfo);
                $resSimptyData['cate_id'] = 0;
                $amazonBaseNodeTypeModel->add($resSimptyData);
            }
        }
        if($allData['xsd:complexType']) {
            $complexTypeData = $this->dealDataElement($allData['xsd:complexType']);
            foreach($complexTypeData as $v) {
                $this->dealComplexType($v, 0, $amazonBaseNodeTypeModel);
            }
        }
    }

    /**
     *
     */

    # 分类属性xml文件入库
    public function parseCategoryXmlFile()
    {
        $dir = 'C:\Users\Administrator.USER-20170118OD\Desktop\am\newXsd1/';
        libxml_disable_entity_loader(true);
        $kkkkk = <<<KKKK
            <xsd:element ref="ClothingAccessories"/>
            <xsd:element ref="ProductClothing"/>
            <xsd:element ref="Miscellaneous"/>
            <xsd:element ref="CameraPhoto"/>
            <xsd:element ref="Home"/>
            <xsd:element ref="Sports"/>
            <xsd:element ref="SportsMemorabilia"/>
            <xsd:element ref="EntertainmentCollectibles"/>
            <xsd:element ref="HomeImprovement"/>
            <xsd:element ref="Tools"/>
            <xsd:element ref="FoodAndBeverages"/>
            <xsd:element ref="Gourmet"/>
            <xsd:element ref="Jewelry"/>
            <xsd:element ref="Health"/>
            <xsd:element ref="CE"/>
            <xsd:element ref="Computers"/>
            <xsd:element ref="SWVG"/>
            <xsd:element ref="Wireless"/>
            <xsd:element ref="Beauty"/>
            <xsd:element ref="Office"/>
            <xsd:element ref="MusicalInstruments"/>
            <xsd:element ref="AutoAccessory"/>
            <xsd:element ref="PetSupplies"/>
            <xsd:element ref="ToysBaby"/>
            <xsd:element ref="Baby"/>
            <xsd:element ref="TiresAndWheels"/>
            <xsd:element ref="Music"/>
            <xsd:element ref="Video"/>
            <xsd:element ref="Lighting"/>
            <xsd:element ref="LargeAppliances"/>
            <xsd:element ref="FBA"/>
            <xsd:element ref="Toys"/>
            <xsd:element ref="GiftCards"/>
            <xsd:element ref="LabSupplies"/>
            <xsd:element ref="RawMaterials"/>
            <xsd:element ref="PowerTransmission"/>
            <xsd:element ref="Industrial"/>
            <xsd:element ref="Shoes"/>
            <xsd:element ref="Motorcycles"/>
            <xsd:element ref="MaterialHandling"/>
            <xsd:element ref="MechanicalFasteners"/>
            <xsd:element ref="FoodServiceAndJanSan"/>
            <xsd:element ref="WineAndAlcohol"/>
            <xsd:element ref="EUCompliance"/>
            <xsd:element ref="Books"/>
            <xsd:element ref="AdditionalProductInformation"/>
            <xsd:element ref="Arts"/>
            <xsd:element ref="Luggage"/>
            <xsd:element ref="Outdoors"/>
            <xsd:element ref="Coins"/>
            <xsd:element ref="Furniture"/>
            <xsd:element ref="LuxuryBeauty"/>
            <xsd:element ref="Collectibles"/>
            <xsd:element ref="ProfessionalHealthCare"/>
            <xsd:element ref="ThreeDPrinting"/>
KKKK;
//        $kkkkk = '<xsd:element ref="Health"/>';
        $fileNameArr = array();
        preg_match_all('/(?<=ref=\")(?:.*?)(?=\")/m', $kkkkk, $fileNameArr);
        $fileNameArr = $fileNameArr[0];

        $amazonCategoryModel = new AmazonCategoryModel();
        $amazonNodeOptionsModel = new AmazonNodeOptionsModel();
        $amazonCategoryNodeModel = new AmazonCategoryNodeModel();
        foreach($fileNameArr as $fileName) {
            $file                       = $dir . $fileName . '.xsd';
            $xml                        = file_get_contents($file);
            echo $xml;
            if(empty($xml)) {
                echo $file . '文件找不到' . '<br/>';
                continue;
            }
            $xml = preg_replace('/^.*?(?=<xsd:element)/ms', '<document>', $xml, 1);
            $xml = preg_replace('/<\/xsd:schema>/ms', '</document>', $xml, 1);
            $obj                        = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
            $allData                       = json_decode(json_encode($obj), true);
            $data = $allData['xsd:element'];
            if(empty($data)) {
                echo $file . '数据为空' . '<br/>';
                continue;
            }
            echo '<br/>' . $file . '文件开始解析分类' . '<br/>';
            $saveData = array();
            # 一个文件就是一个分类   pid放在循环外面  循环内处处可用
            $this->pid = 0;
            $this->cateId = 0;
            $this->flagNum = 1;
            $data = $this->dealDataElement($data);
            foreach ($data as $product) {
                $this->dealSingleCateData($product);
            }
//            continue;
            # 处理element外的其他信息
            $this->pid = $this->pid ? : $this->cateId;
            $amazonBaseNodeTypeModel = new AmazonBaseNodeTypeModel();
            if($allData['xsd:simpleType']) {
                $simptyData = $this->dealDataElement($allData['xsd:simpleType']);
                foreach($simptyData as $simptyInfo) {
                    $resSimptyData = $this->dealSimpleType($simptyInfo);
                    $resSimptyData['cate_id'] = $this->pid;
                    $amazonBaseNodeTypeModel->add($resSimptyData);
                    if($this->categoryType && $this->categoryType === $resSimptyData['node_name']) {
                        $cates = json_decode($resSimptyData['options'], true);
                        $tmpData = array();
                        foreach($cates as $cate) {
                            $tmpData[] = ['name' => $cate, 'pid' => $this->pid];
                        }
                        $amazonCategoryModel->addAll($tmpData);
                    }
                }
            }
            if($allData['xsd:complexType']) {
                $complexTypeData = $this->dealDataElement($allData['xsd:complexType']);
                foreach($complexTypeData as $v) {
                    $this->dealComplexType($v, 0, $amazonBaseNodeTypeModel);
                }
            }
        }
    }

    public function dealDataElement($data)
    {
        return isset($data['@attributes']) ? [$data] : $data;
    }

    # 处理单个分类信息
    public function dealSingleCateData($product)
    {
        $amazonCategoryModel = new AmazonCategoryModel();
        $amazonCategoryNodeModel = new AmazonCategoryNodeModel();
        # 保存分类信息
        $cateName = $product['@attributes']['name'];

        if ($this->pid == 0) {
            $pid = $this->cateId ? : 0;
        } else {
            $pid = $this->pid;
        }
        $where = [
            'name' => $cateName,
            'pid' => $pid,
        ];
        $info = $amazonCategoryModel->where($where)->find();
        if ($info) {
            $this->pid = $info['pid'];
            $this->cateId = $info['id'];
            $this->dealType($product, 0, $amazonCategoryNodeModel);
            if (!empty($this->tmpCateData)) {
                $data = $this->tmpCateData;
                $this->tmpCateData = array();
                foreach ($data as $v4) {
                    $this->dealSingleCateData($v4);
                }
            }
        } else {
            // 节点不是分类
            $amazonBaseNodeTypeModel = new AmazonBaseNodeTypeModel();
            $product = $this->dealDataElement($product);
            $this->dealType($product,0,$amazonBaseNodeTypeModel);
        }

        // 跑分类
//        if ($this->flagNum === 1) {
//            $this->flagNum++;
//            $this->cateId = $this->saveCategoryData($product, $cateName, $this->pid);
//        } else {
//            if ($this->pid === 0) {
//                $this->pid = $this->cateId;
//            }
//            # 非第一次进入则从表中查出分类id
//            $this->cateId = $amazonCategoryModel->getCateIdByCateName($cateName);
//        }

    }

    public function hasChildCategory($product)
    {
        $elements = $this->dealDataElement($product['xsd:complexType']['xsd:sequence']['xsd:element']);
//        if($product['xsd:complexType']['xsd:sequence']['xsd:element']['@attributes']['name'] == 'ProductType') {
//            return $product['xsd:complexType']['xsd:sequence']['xsd:element'];
//        }
        foreach($elements as $k=>$v) {
            if($v['@attributes']['name'] == 'ProductType') {
                if(isset($v['@attributes']['type'])) {
                    return ['type' => $v['@attributes']['type']];
                }
                return $v;
            }
        }
        return false;
    }

    # 处理分类
    public function saveCategoryData($product, $cate_name, $pid = 0)
    {
        $amazonCategoryModel = new AmazonCategoryModel();
        # 若父ID为0 则为第一次进入  而父分类保存在第一个节点中  第一次进入则处理分类信息
        # 存在则表示有子分类
//        debug($product);die;
        $elementData = $this->hasChildCategory($product);
        if ($elementData !== false) {
            $tmpData1 = ['name' => $cate_name, 'pid' => $pid,];
            $relateType = 2;
            if($elementData['type']) {
                $tmpData1['relate_type'] = $relateType;
                $pid     = $amazonCategoryModel->add($tmpData1);
                $this->categoryType = $elementData['type'];
            } else {
                $childs  = array();
                if (current(current($elementData['xsd:complexType']))) {
                    $relateType = 1;
                    $tmpData = current(current($elementData['xsd:complexType']));
                } else if (current($elementData['xsd:simpleType'])['xsd:enumeration']) {
                    $tmpData = current($elementData['xsd:simpleType'])['xsd:enumeration'];
                } else {
                    $tmpData = [];
                }
                $tmpData1['relate_type'] = $relateType;
                $pid     = $amazonCategoryModel->add($tmpData1);
//                $tmpData = current(current($elementData['xsd:complexType'])) ? : current($elementData['xsd:simpleType'])['xsd:enumeration'];
                foreach ($tmpData as $v1) {
                    $child = current($v1['@attributes'] ? : $v1);
                    if(is_string($child)) {
                        $childs[] = $child;
                    }
                }
                $tmpData = array();
                foreach($childs as $v2) {
                    $tmpData[] = ['name' => $v2, 'pid' => $pid];
                }
                $amazonCategoryModel->addAll($tmpData);
            }
        } else {   //否则此分类下无子分类
            $tmpData = ['name' => $cate_name, 'pid' => $pid];
            $pid     = $amazonCategoryModel->add($tmpData);
        }
        return $pid;
    }


    # 亚马逊真恶心，tmd 解个xsd还需要递归  把各种情况的处理函数都抽取出来用作递归
    public function dealType($product, $pNodeId = 0, $model)
    {
        if(isset($product['xsd:complexType'])) {
            $elements = $this->dealDataElement($product['xsd:complexType']['xsd:sequence']['xsd:element']);
        } else if(isset($product['xsd:sequence']['xsd:element'])){
            $elements = $this->dealDataElement($product['xsd:sequence']['xsd:element']);
        } else {
            $elements = $product;
        }
        foreach($elements as $element) {
            if (isset($element['xsd:complexType'])) {
                $this->dealComplexType($element, $pNodeId, $model);
            } else if (isset($element['xsd:simpleType'])) {
                $save = $this->dealSimpleType($element, $pNodeId, $model);
                $model->add($save);
            } else {
                $save = $this->dealBaseType($element, $pNodeId, $model);
                $model->add($save);
            }
        }
    }

    public function dealSimpleType($product, $pNodeId = 0, $model = null)
    {
        $amazonCategoryModel = new AmazonCategoryModel();
//        $amazonNodeOptionsModel = new AmazonNodeOptionsModel();
        $amazonCategoryNodeModel = new AmazonCategoryNodeModel();
        $save = $this->dealAttributeNode($product, $pNodeId, $model);
        $save = array_merge($save, $this->dealAttributeNode($product['xsd:simpleType']['xsd:restriction'], $pNodeId, $model));

        $restriction = $product['xsd:simpleType']['xsd:restriction'] ? : $product['xsd:restriction'];
        if(isset($restriction['xsd:enumeration'])) {
            $enumData = $restriction['xsd:enumeration'];
            $options = $this->enumType($enumData);
            $save['options'] = json_encode($options);
        }
        if($restriction['xsd:maxLength']) {
            $save['max_length'] = $restriction['xsd:maxLength']['@attributes']['value'];
        }
        if($restriction['xsd:minLength']) {
            $save['min_length'] = $restriction['xsd:minLength']['@attributes']['value'];
        }
        if($restriction['xsd:minInclusive']) {
            $save['min_length'] = $restriction['xsd:minInclusive']['@attributes']['value'];
        }
        if($restriction['xsd:maxInclusive']) {
            $save['max_length'] = $restriction['xsd:maxInclusive']['@attributes']['value'];
        }
        $restriction['@attributes']['base'] && $save['node_type'] = $restriction['@attributes']['base'];
        if(isset($product['xsd:union'])) {
            if(isset($product['xsd:union']['@attributes']['memberTypes'])) {
                if ($save['node_type']) {
                    echo '<br/>多个属性信息错误<br/>';
                } else {
                    $save['node_type'] = $product['xsd:union']['@attributes']['memberTypes'];
                }
            }
        }
        
        return $save;
    }

    public function dealBaseType($product, $pNodeId, $model = null)
    {
//        $amazonCategoryNodeModel = new AmazonCategoryNodeModel();
        $save = $this->dealAttributeNode($product, $pNodeId, $model);
        return $save;
    }

    /**
     * xsd:complexType : 复合类型
     * @param $complexData 复合类型数据
     * @param $pid  分类父ID
     * @param $pNodeId  父节点ID
     */
    public function dealComplexType($complexData, $pNodeId = 0, $model)
    {
        $save                    = $this->dealAttributeNode($complexData, $pNodeId, $model);
        if ($save['node_name'] === 'ProductType') {   //特殊处理producttype
            $tmpData = $this->dealDataElement(current(current($complexData['xsd:complexType'])) ?: current($complexData['xsd:simpleType'])['xsd:enumeration'], $pNodeId);
            foreach ($tmpData as $v1) {   //['xsd:choice']['xsd:element']
                $child = current($v1['@attributes'] ?: $v1);
                if (is_string($child)) {
                    $childs[]        = $child;
                    $save['options'] = json_encode($childs);
                }
                if (isset($v1['xsd:complexType'])) {
                    $this->tmpCateData[] = $v1;
                }
            }
            $model->add($save);
        } else {
            $flag = false;
            $dealData = $complexData['xsd:complexType'] ? : $complexData;  //为了兼容ele外的complex
            if(isset($dealData['xsd:simpleContent']['xsd:extension'])) {
                $extension = $dealData['xsd:simpleContent']['xsd:extension'];
                isset($extension['@attributes']['base']) && $save['node_type'] = $extension['@attributes']['base'];
                if(isset($extension['xsd:attribute'])) {
                    $save['attributes'] = $this->dealAttribute($extension);
                }
            }
            if(isset($dealData['xsd:attribute'])) {
                $save['attributes'] = $this->dealAttribute($dealData);
            }
            if (isset($dealData['xsd:sequence'])) {
                //sequence标记,由于复杂类型的节点下面还有节点，用node表中的p_node_id标识父节点
                # 先把父节点保存，得到p_node_id
                $p_node_id = $model->add($save);
                $flag = true;
                $this->dealType($complexData, $p_node_id, $model);
            }
            if (! $flag) {
                $model->add($save);
            }
        }
    }

    # 处理attribute
    public function dealAttribute($data)
    {
        $attributes = $this->dealDataElement($data['xsd:attribute']);
        $saveAttribute = array();
        foreach($attributes as $k => $attribute) {
            if(isset($attribute['xsd:simpleType'])) {
                $saveAttr = $this->dealSimpleType($attribute);
            } else if(isset($attribute['xsd:complexType'])) {
                debug($attribute);
            } else {
                $saveAttr = $this->dealBaseType($attribute, 0);
            }
            unset($saveAttr['cate_id']);
            $saveAttribute[] = $saveAttr;
        }
        return json_encode($saveAttribute);
    }


    # 类型之枚举
    public function enumType($enumData)
    {
        if(isset($enumData['@attributes'])) {
            $enumData = [$enumData];
        }
        $options = array();
        foreach($enumData as $v) {
            $options[] = $v['@attributes']['value'];
        }
        return $options;
    }


    # 处理属性节点  @attribute
    public function dealAttributeNode($data, $pNodeId, $model = null)
    {
        $attributes = $data['@attributes'];
        $returnData = [];
        if($pNodeId) {
            $returnData['p_node_id'] = $pNodeId;
        } else {
            $returnData['cate_id'] = $model instanceof AmazonBaseNodeTypeModel ? $this->pid : $this->cateId;
        }
        $returnData['file_id'] = $this->pid ? : $this->cateId;
        isset($attributes['name']) && $returnData['node_name'] = $attributes['name'];
        isset($attributes['type']) && $returnData['node_type'] = $attributes['type'];
        isset($attributes['ref']) && $returnData['ref'] = $attributes['ref'];
        isset($attributes['minOccurs']) && $returnData['min_occurs'] = $attributes['minOccurs'];
        isset($attributes['maxOccurs']) && $returnData['max_occurs'] = $attributes['maxOccurs'] === 'unbounded' ? 99 : $attributes['maxOccurs'];
        isset($attributes['base']) && $returnData['node_type'] = $attributes['base'];
        isset($data['xsd:annotation']['xsd:documentation']) && $returnData['documentation']
            = preg_replace('/\n|\s{3,}/ms', '', $data['xsd:annotation']['xsd:documentation']);
        return $returnData;
    }

    # 清库
    public function clearTab()
    {
        $amazonCategoryModel = new AmazonCategoryModel();
        $amazonCategoryModel->query('truncate sys_amazon_category_node');
        $amazonCategoryModel->query('truncate sys_amazon_node_options');
        $amazonCategoryModel->query('truncate sys_amazon_base_node_type');
    }

    public function deal()
    {
        $this->clearTab();
        set_time_limit(0);
        $this->parseBaseFile();
        $this->parseCategoryXmlFile();
        $this->confirmAttrCanDis();
        $this->confirmAttrCanDis1();
        $this->changeCheckType();
        $this->createAttributesInfo();
        $this->confirmAttrbuteCanDis();
        $this->confirmAttrbuteCanDis1();
        $this->dealNodeCateId();
        $this->reappearGenerateAttrRequireLevel();
    }


    # 确定属性是否能直接展示
    public function confirmAttrCanDis()
    {
        $amazonCategoryNodeModel = new AmazonCategoryNodeModel();
        $amazonBaseNodeTypeModel = new AmazonBaseNodeTypeModel();
        $data = $amazonCategoryNodeModel->select();
        foreach($data as $v) {
            $where = ['id' => $v['id']];
            if ($v['options'] !== '') {
                $saveData = [
                    'is_display_type' => 1,
                    'required_level' => $this->getRequiredLevel($v['min_occurs'], 1),
                ];
            } else if ($v['ref'] !== '') {
                $saveData = [
                    'is_display_type' => 0,
                    'required_level' => $this->getRequiredLevel($v['min_occurs'], 0),
                ];
            } else if ($amazonCategoryNodeModel->where('p_node_id="' . $v['id'] . '"')->count()) {
                $saveData = [
                    'is_display_type' => 0,
                    'required_level' => $this->getRequiredLevel($v['min_occurs'], 0),
                    'has_child_node' => 1,
                ];
            } else if ($v['node_type'] === '') {
                $saveData = [
                    'is_display_type' => 1,
                    'check_type' => 'xsd:string',
                    'required_level' => $this->getRequiredLevel($v['min_occurs'], 0),
                ];
            } else if (substr($v['node_type'], 0, 3) === 'xsd') {
                $saveData = [
                    'is_display_type' => 1,
                    'check_type' => $v['node_type'],
                ];
                if ($v['node_type'] === 'xsd:boolean') {
                    $saveData['required_level'] = $this->getRequiredLevel($v['min_occurs'], 1);
                    $saveData['options'] = json_encode(['true', 'false']);
                } else {
                    $saveData['required_level'] = $this->getRequiredLevel($v['min_occurs'], 0);
                }
            } else {
                $map = [
                    'node_name' => $v['node_type'],
                    'cate_id' => ['in', '0,' . $v['file_id']],
                ];
                if (! $typeData = $amazonBaseNodeTypeModel->where($map)->find()) {
                    echo '<br/>' . $v['id'] . '类型找不到<br/>';
                    continue;
                }
                $saveData = [
                    'is_display_type' => 0,
                    'required_level' => $this->getRequiredLevel($v['min_occurs'], 0),
                ];
            }
            $amazonCategoryNodeModel->where($where)->save($saveData);
        }
    }

    public function confirmAttrCanDis1()
    {
        $amazonBaseNodeTypeModel = new AmazonBaseNodeTypeModel();
        $data = $amazonBaseNodeTypeModel->select();
        foreach($data as $v) {
            $where = ['id' => $v['id']];
            if ($v['options'] !== '') {
                $saveData = [
                    'is_display_type' => 1,
                    'required_level' => $this->getRequiredLevel($v['min_occurs'], 1),
                ];
            } else if ($v['ref'] !== '') {
                $saveData = [
                    'is_display_type' => 0,
                    'required_level' => $this->getRequiredLevel($v['min_occurs'], 0),
                ];
            } else if ($amazonBaseNodeTypeModel->where('p_node_id="' . $v['id'] . '"')->count()) {
                $saveData = [
                    'is_display_type' => 0,
                    'required_level' => $this->getRequiredLevel($v['min_occurs'], 0),
                    'has_child_node' => 1,
                ];
            } else if ($v['node_type'] === '') {
                $saveData = [
                    'is_display_type' => 1,
                    'check_type' => 'xsd:string',
                    'required_level' => $this->getRequiredLevel($v['min_occurs'], 0),
                ];
            } else if (substr($v['node_type'], 0, 3) === 'xsd') {
                $saveData = [
                    'is_display_type' => 1,
                    'check_type' => $v['node_type'],
                ];
                if ($v['node_type'] === 'xsd:boolean') {
                    $saveData['required_level'] = $this->getRequiredLevel($v['min_occurs'], 1);
                    $saveData['options'] = json_encode(['true', 'false']);
                } else {
                    $saveData['required_level'] = $this->getRequiredLevel($v['min_occurs'], 0);
                }
            } else {
                $saveData = [
                    'is_display_type' => 0,
                    'required_level' => $this->getRequiredLevel($v['min_occurs'], 0),
                ];
            }
            $amazonBaseNodeTypeModel->where($where)->save($saveData);
        }
    }

    # 判断属性required_level
    public function getRequiredLevel($minOccurs, $isOption)
    {
        if ($minOccurs == -1 || $minOccurs == 1) {
            if($isOption === 1) {
                return 2;    //必须可选
            }
            return 3;   //必须不可选
        } else {
            if($isOption === 1) {
                return 0;    //不必须可选
            }
            return 1;   //不必须不可选
        }
    }

    # 改变数据类型
    public function changeCheckType()
    {
        $amazonBaseNodeTypeModel = new AmazonBaseNodeTypeModel();
        $amazonCategoryNodeModel = new AmazonCategoryNodeModel();
        $where = [
            'is_display_type' => 1,
            'options' => '',
            'check_type' => ['like', '%string%']
        ];
        $amazonBaseNodeTypeModel->where($where)->setField('check_type', 'xsd:string');
        $amazonCategoryNodeModel->where($where)->setField('check_type', 'xsd:string');
    }

    public function createAttributesInfo()
    {
        $amazonBaseNodeTypeModel = new AmazonBaseNodeTypeModel();
        $amazonCategoryNodeModel = new AmazonCategoryNodeModel();
        $models = [$amazonBaseNodeTypeModel, $amazonCategoryNodeModel];
        foreach ($models as $model) {
            $where = ['attributes' => ['neq', '']];
            $data = $model->where($where)->getField('id,attributes', true);
            foreach ($data as $id => $v) {
                $attributeData = json_decode($v, true);
                if (empty($attributeData)) {
                    continue;
                }
                foreach ($attributeData as $k1 => $v1) {
                    if (isset($attributeData[$k1]['options'])) {
                        $attributeData[$k1]['required_level'] = 2;
                    } else {
                        $attributeData[$k1]['required_level'] = 3;
                    }
                }
                $model->where('id="' . $id . '"')->save(['attributes' => json_encode($attributeData)]);
            }
        }
    }

    public function confirmAttrbuteCanDis()
    {
        $amazonCategoryNodeModel = new AmazonCategoryNodeModel();
        $amazonBaseNodeTypeModel = new AmazonBaseNodeTypeModel();
        $where = ['attributes' => ['neq', '']];
        $data = $amazonCategoryNodeModel->where($where)->getField('id,attributes,file_id', true);
        foreach($data as $id => $v) {
            $attributeData = json_decode($v['attributes'], true);
            if (empty($attributeData)) {
                continue;
            }
            foreach ($attributeData as $k => $info) {
                if ($info['ref'] !== '') {
                    $saveData = [
                        'is_display_type' => 0,
                    ];
                } else if ($info['node_type'] === '') {
                    $saveData = [
                        'is_display_type' => 1,
                        'check_type' => 'xsd:string',
                    ];
                } else if (substr($info['node_type'], 0, 3) === 'xsd') {
                    $saveData = [
                        'is_display_type' => 1,
                        'check_type' => $info['node_type'],
                    ];
                    if ($v['node_type'] === 'xsd:boolean') {
                        $info['options'] = json_encode(['true', 'false']);
                    }
                } else {
                    $map = [
                        'node_name' => $v['node_type'],
                        'cate_id' => ['in', '0,' . $v['file_id']],
                    ];
                    if (!$typeData = $amazonBaseNodeTypeModel->where($map)->find()) {
                        echo '<br/>' . $id . '类型找不到<br/>';
                        continue;
                    }
                    $saveData = [
                        'is_display_type' => 0,
                    ];
                }
            }
            $attributeData[$k] = array_merge($attributeData[$k], $saveData);
            $amazonCategoryNodeModel->where('id="'.$id.'"')->save(['attributes' => json_encode($attributeData)]);
        }
    }

    public function confirmAttrbuteCanDis1()
    {
        $amazonCategoryNodeModel = new AmazonCategoryNodeModel();
        $amazonBaseNodeTypeModel = new AmazonBaseNodeTypeModel();
        $where = ['attributes' => ['neq', '']];
        $data = $amazonBaseNodeTypeModel->where($where)->getField('id,attributes', true);
        foreach($data as $id => $v) {
            $attributeData = json_decode($v, true);
            if (empty($attributeData)) {
                continue;
            }
            foreach ($attributeData as $k => $info) {
                if ($info['ref'] != '') {
                    $saveData = [
                        'is_display_type' => 0,
                    ];
                } else if ($info['node_type'] === '') {
                    $saveData = [
                        'is_display_type' => 1,
                        'check_type' => 'xsd:string',
                    ];
                } else if (substr($info['node_type'], 0, 3) === 'xsd') {
                    $saveData = [
                        'is_display_type' => 1,
                        'check_type' => $info['node_type'],
                    ];
                    if ($v['node_type'] === 'xsd:boolean') {
                        $info['options'] = json_encode(['true', 'false']);
                    }
                } else {
                    $saveData = [
                        'is_display_type' => 0,
                    ];
                }
                $attributeData[$k] = array_merge($attributeData[$k], $saveData);
                if ($attributeData[$k]['file_id'] === null) {
                    $attributeData[$k]['file_id'] = 0;
                }
            }
            $amazonBaseNodeTypeModel->where('id="'.$id.'"')->save(['attributes' => json_encode($attributeData)]);
        }
    }

    public function dealNodeCateId()
    {
        $amazonBaseNodeTypeModel = new AmazonBaseNodeTypeModel();
        $where = [
            'cate_id' => 0,
            'p_node_id' => ['neq', -1],
        ];
        $amazonBaseNodeTypeModel->where($where)->setField('cate_id', -1);
    }

    # 重新生成一下attribute的require_level属性
    public function reappearGenerateAttrRequireLevel()
    {
        $amazonCategoryNodeModel = new AmazonCategoryNodeModel();
        $amazonBaseNodeTypeModel = new AmazonBaseNodeTypeModel();
        $models = [$amazonCategoryNodeModel, $amazonBaseNodeTypeModel];
        foreach ($models as $model) {
            $field = ['id', 'required_level', 'attributes'];
            $data = $model->field($field)->where('attributes <> "" and required_level < 2')->select();
            foreach ($data as $k=>$v) {
                $attributes = json_decode($v['attributes'], true);
                foreach ($attributes as $k1 => $attribute) {
                    $attributes[$k1]['required_level'] -= 2;
                }
                $data[$k]['attributes'] = json_encode($attributes);
                $model->save($data[$k]);
            }
        }
    }


    # 补全sys_amazon_recommended_browse_node_list表us字段信息
    public function createRecommendedUSlist()
    {
        $recommendedBrowseNodeModel = new AmazonRecommendedBrowseNodeListModel();
        $data = $recommendedBrowseNodeModel->getField('id,node_path', true);
        while (list($id, $node_path) = each($data)) {
            $node_paths = explode('/', $node_path);
            if (empty($node_paths)) {
                echo $id, '<br/>';
            }
            $usNode = $node_paths[count($node_paths) - 1];
            $recommendedBrowseNodeModel->save(['id' => $id, 'us' => $usNode]);
        }
    }

    public function uploadFile()
    {
        set_time_limit(0);
        import('@.Service.AmazonKDService');
        $KDService = new AmazonKDService();
        $KDService->uploadFile();
    }

}