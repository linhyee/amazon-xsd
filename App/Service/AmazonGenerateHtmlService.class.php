<?php


class AmazonGenerateHtmlService
{
    private $_cateId = null;
    private $_catePid = null;
    private $cateNameTree = null;
    private $inputNum = 0;
    private $noRequireAttr = array('ProductType');
    public $noDisplayAttr = array();

    public function __construct($cateId)
    {
        $categoryModel = new AmazonCategoryModel();
        $cateInfo = $categoryModel->where('id="'.$cateId.'"')->field('pid,name')->find();
        $this->cateNameTree = $categoryModel->getCategoryTreeByChildId($cateId);
        $this->_catePid = $cateInfo['pid'] ? : $cateId;
        $this->_cateId = $cateInfo['pid'] ? $cateId : null;
    }

    public function getCategoryDisplayHtmlByCateId()
    {
        # 获取父类属性HTML
        $displayParentData = $this->getDisplayData($this->_catePid);
        # 获取子类属性HTML
        if($this->_cateId) {
            $displayChildData = $this->getDisplayData($this->_cateId);
        }
        # 若父节点信息跟子节点信息出现节点名重复，则以子节点信息为主
        list($displayChildData, $displayParentData) = $this->deleteRepeatNode($displayChildData, $displayParentData);
        $html = [];
        foreach ($displayParentData as $needData) {
            $html[] = $this->getHtmlByDataType($needData);
        }
        foreach ($displayChildData as $needData) {
            $html[] = $this->getHtmlByDataType($needData, true);
        }
        # 将html数组拆分成2部分， 左右显示
        $seperateHtml = array(
            'left' => '',
            'right' => '',
        );
        foreach($html as $k=>$v) {
            if($k%2) {
                $seperateHtml['right'] .= $v;
            } else {
                $seperateHtml['left'] .= $v;
            }
        }
        return $seperateHtml;
    }

    # 取出父类跟子类的重复节点
    public function deleteRepeatNode($displayChildData, $displayParentData)
    {
        foreach ($displayChildData as $k1 => $childInfo) {
            foreach ($displayParentData as $k2 => $parentInfo) {
                if ($childInfo['node_name'] === $parentInfo['node_name']) {
                    if ($parentInfo['required_level'] < 2) {
                        unset($displayParentData[$k2]);
                        break;
                    } else if ($childInfo['required_level'] < 2) {
                        unset($displayChildData[$k2]);
                        break;
                    }
                }
            }
        }
        return [$displayChildData, $displayParentData];
    }

    # 判断属性是否需要展示
    public function isRequireDisplay($attrName)
    {
        if(in_array($attrName, $this->noRequireAttr)) {
            $this->noDisplayAttr[] = $attrName;
            return false;
        } else {
            return true;
        }
    }

    /**
     * 根据分类id获取展示信息数组
     * @param $cateId
     * @return array|mixed
     */
    public function getDisplayData($cateId)
    {
        $key = "Amazon:{$cateId}:DisplayData";
        if ($displayData = $this->getCacheData($key)) {
            return $displayData;
        }
        $categoryNodeModel = new AmazonCategoryNodeModel();
        $nodeDatas = $categoryNodeModel->getNodeDataByCate($cateId);
        foreach($nodeDatas as $nodeData) {
            if(! $this->isRequireDisplay($nodeData['node_name'])) {
                continue;
            }
            $displayData[] = $this->nodeData2DisplayData($nodeData, $cateId, $nodeData['file_id']);
        }
        $this->setCacheData($displayData, $key);
        return $displayData;
    }

    # 根据数据库记录 将属性信息解析成展示信息
    public function nodeData2DisplayData($nodeData, $cateId, $fileId)
    {
        $baseNodeTypeModel = new AmazonBaseNodeTypeModel();
        $categoryNodeModel = new AmazonCategoryNodeModel();
        # 如果含有ref，那个此节点只是一个占位符，把ref具体信息查出来代替nodeData
        if (isset($nodeData['ref']) && $nodeData['ref'] !== '') {
            $required_level = $nodeData['required_level'];
            $nodeData = $this->getNodeData($nodeData, $baseNodeTypeModel, 'ref', $fileId, 'allField');
            $nodeData['required_level'] = $required_level;
            # 标识信息来源，用于查找子节点
            $nodeData['from'] = 'base';
        }
        # 只是一些基本信息
        $node_name = $nodeData['node_name'];
        $displayData = array(
            'node_name' => $node_name,
            'datatype' => 'xsd:string',
            'required_level' => $nodeData['required_level'],
        );

        if(isset($nodeData['is_display_type']) && $nodeData['is_display_type'] == 0) {       //不是最后一级，就是说还需要递归
            if ($nodeData['has_child_node'] == 1) {   //含有子节点
                if ($nodeData['from'] === 'base') {
                    $childNodeModel = $baseNodeTypeModel;
                } else {
                    $childNodeModel = $categoryNodeModel;
                }
                # 通过p_node_id查找子节点信息
                $childNodeData = $this->getNodeData($nodeData, $childNodeModel, 'childNode', $fileId, 'allField');
                # 分别对子节点递归
                foreach($childNodeData as $v) {
                    # 标识信息来源，用于查找子节点
                    $v['from'] = 'base';
                    $v =  $this->nodeData2DisplayData($v, $cateId, $fileId);
                    $v['file_id'] = $fileId;
                    $data[] = $v;
                }
                $displayData['data'] = $data;
                if ($nodeData['attributes'] !== '') {
                    $attributes = json_decode($nodeData['attributes'], true);
                    $displayData['attributes'] = [];
                    foreach ($attributes as $attribute) {
                        $displayData['attributes'][] = $this->nodeData2DisplayData($attribute, $cateId, $fileId);
                    }
                }
                return $displayData;
            } else {   # 不含子节点
                # 通过node_type类型查找
                $nodeTypeData = $this->getNodeData($nodeData, $baseNodeTypeModel, 'type', $fileId, 'allField');
                # 通过类型查找不产生新的节点，只是规定节点类型而已，节点信息还是原来的，把信息拿过来
                $nodeTypeData['node_name'] = $nodeData['node_name'];
                isset($nodeTypeData['required_level']) && $nodeTypeData['required_level'] = $nodeData['required_level'];
                # 标识信息来源，用于查找子节点
                $nodeTypeData['from'] = 'base';
                if (!empty($nodeData['attributes'])) {
                    $nodeTypeData['attributes'] = $nodeData['attributes'];
                }
                return $this->nodeData2DisplayData($nodeTypeData, $cateId, $fileId);
            }
        } else {      // 最后一级
//            可能会出现的类型
//            xsd:date
//            xsd:dateTime
//            xsd:decimal
//            xsd:integer
//            xsd:nonNegativeInteger
//            xsd:positiveInteger
//            xsd:string
            if ($nodeData['options']) {
                $displayData['type'] = 'select';
                $displayData['options'] = json_decode($nodeData['options'], true);
                if ($node_name === 'VariationTheme') {
                    $displayData['options'] = $this->dealVariationThemeOptions($displayData['options']);
                }
            } else {
                if ($node_name === 'VariationTheme') {  // 若不存在选项，则默认Size,Color,SizeColor
                    $displayData['options'] = $this->addVariationThemeOptions($cateId);
                    $displayData['type'] = 'select';
                } else {
                    $displayData['type'] = 'text';
                    $displayData['datatype'] = $nodeData['check_type'];
                }
            }
            if ($nodeData['attributes'] !== '' && $attributes = json_decode($nodeData['attributes'], true)) {
                $displayData['attributes'] = [];
                foreach ($attributes as $attribute) {
                    $displayData['attributes'][] = $this->nodeData2DisplayData($attribute, $cateId, $fileId);
                }
            }
            return $displayData;
        }
    }

    /**
     * 处理多属性主题选项
     * @param $options
     * @return mixed
     */
    public function dealVariationThemeOptions($options)
    {
        // VariationTheme中有很多重复的，把已比较过的记录下来
        $bindinds = [];
        // 获取分类节点树
        $nodeTree = $this->getCategoryNodeTree();
        foreach ($options as $key => $option) {
            if ($option === 'SizeColor' || $option === 'ColorSize' ) {
                $needs = ['Size', 'Color'];
            } else {
                $needs = explode('-', $option);
            }
            foreach ($needs as $need) {
                if (isset($bindinds[$need])) {
                    $res = $bindinds[$need];
                } else {
                    $res = $this->isInArrayKey($need, $nodeTree);
                    if (! $res && ($need === 'Size' || $need === 'Color')) {
                        $need = $need . 'Name';
                        $res = $this->isInArrayKey($need, $nodeTree);
                    }
                    $bindinds[$need] = $res;
                }
                if ( ! $res) {
                    unset($options[$key]);
                    continue 2;
                }
            }
        }
        return $options;
    }

    public function addVariationThemeOptions($cateId)
    {
        // 获取分类节点树
        $options = ['Size', 'Color', 'SizeColor', 'SizeName', 'ColorName'];
        $options = $this->dealVariationThemeOptions($options);
        return $options;
    }

    # 获取数组中值为非数组的key
    public function getSingleArrKey($data)
    {
        $keys = array();
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                continue;
            }
            $keys[] = $k;
        }
        return $keys;
    }

    /**
     * 判断一个值是否在一个多维数组中
     * @param $key
     * @param $arr
     * @return bool
     */
    public function isInArrayKey($key, $arr)
    {
        foreach ($arr as $k => $val) {
            if (strcasecmp($k, $key) === 0) {
                return true;
            } else if (is_array($val)) {
                if ($this->isInArrayKey($key, $val)) {
                    return true;
                }
            }
        }
        return false;
    }

    # 根据类型获取HTML
    public function getHtmlByDataType($data, $isChild = false)
    {
        $isrequired = $data['required_level'] < 2 ? false : true;
        $html = '<tr>';
        if ($isrequired) {
            $html .= '<td class="titlesDiv" xmlNodeRequired="1"><div style="margin:0;">';
        } else {
            $html .= '<td class="titlesDiv" xmlNodeRequired="2"><div style="margin:0;">';
        }
        $html .= '<div class="clearFloat"></div>';
        $html .= '<table attributeName="'.$data['node_name'].'" class="titleListTable' . $this->account . '"><tbody>';
        $html .= '<tr>';
        $html .= '<td colspan="2" class="attrTitle"><span class="attrname">'.$data['node_name'].'</span><span style="color:red;padding:0 3px;font-size:15px;">';
        if ($isrequired) {
            $inputName = substr($this->getInputName('', $data['node_name'], $isChild), 0, -1) . '_ph]';
            $html .= "*<input type='hidden' name='{$inputName}' value='placeholde' />";
        }
        $html .= '</span><div class="floatR"></div></td></tr><tr>';
        $this->inputNum = 0;
        $html .= $this->getInputHtml($data, '', $isChild);
        $html .= '</tr>';
        $html .= '</tbody></table></div></td></tr>';
        return $html;
    }

    # 生成表单元素
    public function getInputHtml($data, $input_name = '', $isChild)
    {
        $html = '';
        $input_name = $this->getInputName($input_name, $data['node_name'], $isChild);
        if(isset($data['type'])) {
            $this->inputNum++;
            if($this->inputNum !== 1 && $this->inputNum % 2 === 1) {
                $html .= '</tr><tr>';
            }
            $html .= '<td>';
            if ($data['type'] == 'text') {
                $html .= $this->getTextHtml($data, $input_name);
            } else if ($data['type'] == 'select') {
                $html .= $this->getSelectHtml($data, $input_name);
            }
            $html .= '</td>';
        } else {
            foreach($data['data'] as $v) {
                $html .= $this->getInputHtml($v, $input_name);
            }
        }
        if (isset($data['attributes'])) {
            foreach ($data['attributes'] as $v) {
                $attr_input_name = substr($input_name, 0, strlen($input_name) - 1) . '_attribute]';
                $html .= $this->getInputHtml($v, $attr_input_name);
            }
        }
        return $html;
    }

    # 获取input的name
    public function getInputName($input_name, $nodeName, $isChild)
    {
        if($isChild) {
            $input_name = ($input_name ? : 'AAaccountAA[Child]') . '[' . $nodeName . ']';
        } else {
            $input_name = ($input_name ? : 'AAaccountAA[Parent]') . '[' . $nodeName . ']';
        }
        return $input_name;
    }

    # 生成input类型的输入元素
    public function getTextHtml($info, $input_name)
    {
        $inputLimit = $this->getInputLimit($input_name, $info['node_name'], $info['required_level'], $info['datatype']);
        $html = '';
        $isrequired = $info['required_level'] < 2 ? false : true;
        if ($isrequired && strpos($input_name, '_attribute') === false) {
            $html .= '<span style="color: red; font-size: 14px">&nbsp;&nbsp;*</span>';
        } else {
            $html .= '<span style="color: red; font-size: 14px">&nbsp;&nbsp;&nbsp;&nbsp;</span>';
        }
        $html .= '<input node_name="'.$info['node_name'].'" class="insertinput" type="text"' . $inputLimit . ' />&nbsp;&nbsp;';
        return $html;
    }

    # 生成select类型的输入元素
    public function getSelectHtml($info, $input_name)
    {
        $inputLimit = $this->getInputLimit($input_name, $info['node_name'], $info['required_level'], $info['datatype']);
        $html = '';
        $isrequired = $info['required_level'] < 2 ? false : true;
        if ($isrequired && strpos($input_name, '_attribute') === false) {
            $html .= '<span style="color: red; font-size: 16px">&nbsp;&nbsp;*</span>';
        } else {
            $html .= '<span style="color: red; font-size: 16px">&nbsp;&nbsp;&nbsp;&nbsp;</span>';
        }
        $html .= '<select node_name="'.$info['node_name'].'" class="amazonchosen" data-'.$inputLimit.'>';
        $options = $info['options'];
        $html .= '<option value="" ></option>';
        foreach($options as $option) {
            $html .= '<option value="' .$option.'" >'.$option.'</option>';
        }
        $html .= '</select>';
        return $html;
    }

    # 生成表单元素的name和place-holder
    public function getInputLimit($input_name, $unitname = '', $required_level, $datatype)
    {
        $isrequired = $required_level < 2 ? false : true;
        $inputLimit = 'placeholder="' . trim($unitname) . '" ';
        $inputLimit .= ' name="'  . $input_name . '" ';
        $inputLimit .= $isrequired ? ' isRequired="1" ' : '';
        $inputLimit .= $datatype ? ' datatype="' . $datatype . '" ' : ' datatype="string" ';
        return $inputLimit;
    }

    # 获取隐藏信息
    public function getHiddenInput($sku)
    {
        $html = '';
        if($this->goods_type == 1) {
            $isPriVal = 'true';
            $html .= '<input type="hidden" value="'.$sku.'" name="%$account%[MPItem][Product][' . $this->cateNameTree['parentCateName'] . '][variantGroupId]" />';
        } else {
            $isPriVal = 'false';
        }
        $html = '<input type="hidden" value="'.$isPriVal.'" name="%$account%[MPItem][Product][' . $this->cateNameTree['parentCateName'] . '][isPrimaryVariant]" />';
        return $html;
    }

    # 编辑信息时，拼接前端页面的name值，根据name值把信息输出在指定位置
    public function createAttrNameAndValue($attrData, $account, $parentCateName)
    {
        $returnData = array();
        foreach($attrData as $k => $attrInfo) {
            foreach ($attrInfo as $v) {
                $attr_name_arr = explode(',', $v['attr_tree']);
                $name          = $account . '[MPItem][Product][' . $parentCateName . ']';
                foreach ($attr_name_arr as $v1) {
                    $name .= '[' . $v1 . ']';
                }
                $returnData[$k][$name] = $v['attr_value'];
            }
        }
        return $returnData;
    }

    public function createElementNodeData($cateId)
    {
        $sortArr['MPItem'] = array(
            'mart' => 1,'sku' => 1,'wpid' => 1,'Product' => 1,'launchDate' => 1,'discontinueDate' => 1,'price' => array('currency'=>1,'amount'=>1)
        ,'minAdvertisedPrice' => array('currency'=>1,'amount'=>1) ,'isMustShipAlone' => 1,'shippingWeight' => array('value'=>1,'unit'=>1),
        );
        $sortArr['MPItem']['Product'] = $this->getProductTree($cateId);
        return $sortArr;
    }

    /**
     * 获取分类属性节点树
     */
    public function getCategoryNodeTree()
    {
        # 获取父类树
        $parentNodeTree = $this->getNodeTreeByCateId($this->_catePid);
        # 获取子分类属性树
        if($this->_cateId) {
            $childNodeTree = $this->getNodeTreeByCateId($this->_cateId);
            # 将子分类信息拼接到父分类上
            $parentNodeTree['ProductType'] = [
                $this->cateNameTree['categoryName'] => $childNodeTree,
            ];
        }
        # 数组中将父分类名加上  完美
        $productTree[$this->cateNameTree['parentCateName']] = $parentNodeTree;
        # 处理Parentage节点信息
        $this->dealParentageNode($productTree);
        return $productTree;
    }

    /**
     * 处理Parentage节点信息
     */
    public function dealParentageNode(& $productTree)
    {
        switch(true) {
            case !empty($productTree[$this->cateNameTree['parentCateName']]['ProductType'][$this->cateNameTree['categoryName']]['VariationData']['Parentage']) :
                unset($productTree[$this->cateNameTree['parentCateName']]['ProductType'][$this->cateNameTree['categoryName']]['Parentage']);
            case !empty($productTree[$this->cateNameTree['parentCateName']]['ProductType'][$this->cateNameTree['categoryName']]['Parentage']) :
                unset($productTree[$this->cateNameTree['parentCateName']]['VariationData']['Parentage']);
            case !empty($productTree[$this->cateNameTree['parentCateName']]['VariationData']['Parentage']) :
                unset($productTree[$this->cateNameTree['parentCateName']]['Parentage']);
        }
    }

    /**
     * 根据分类ID获取此分类下的所有节点信息
     */
    public function getNodeTreeByCateId($cateId)
    {
        $key = "Amazon:{$cateId}:NodeTree";
        if ($nodeTree = $this->getCacheData($key)) {
            return $nodeTree;
        }
        $categoryNodeModel = new AmazonCategoryNodeModel();
        $attrDatas = $categoryNodeModel->where('cate_id="'.$cateId.'"')
            ->field('id,node_name,node_type,ref,has_child_node,is_display_type,file_id')
            ->select();
        $nodeTree = array();
        foreach($attrDatas as $attrData) {
            $nodeTree = array_merge($nodeTree, $this->getAllChildAttr($attrData));
        }
        $this->setCacheData($nodeTree, $key);
        return $nodeTree;
    }

    /**
     * 根据所传节点信息获取它下面的所有节点信息树
     */
    public function getAllChildAttr($nodeData, $fileId = null)
    {
        # 如果含有ref，那个此节点只是一个占位符，把ref具体信息查出来代替nodeData
        $baseNodeTypeModel = new AmazonBaseNodeTypeModel();
        $categoryNodeModel = new AmazonCategoryNodeModel();
        $fileId = $fileId ? : $nodeData['file_id'];
        if ($nodeData['ref'] !== '') {
            $nodeData = $this->getNodeData($nodeData, $baseNodeTypeModel, 'ref', $fileId);
            # 标识信息来源，用于查找子节点
            $nodeData['from'] = 'base';
        }
        $node_name = $nodeData['node_name'];
        $childAttrTree = array();

        if(isset($nodeData['is_display_type']) && $nodeData['is_display_type'] == 0) {       //不是最后一级，就是说还需要递归
            if ($nodeData['has_child_node'] == 1) {   //含有子节点
                if ($nodeData['from'] === 'base') {
                    $childNodeModel = $baseNodeTypeModel;
                } else {
                    $childNodeModel = $categoryNodeModel;
                }
                # 通过p_node_id查找子节点信息
                $childNodeData = $this->getNodeData($nodeData, $childNodeModel, 'childNode', $fileId);
                # 分别对子节点递归
                foreach($childNodeData as $v) {
                    $v['from'] = 'base';
                    # 标识信息来源，用于查找子节点
                    $childAttrTree = array_merge($childAttrTree,$this->getAllChildAttr($v, $fileId));
                }
                return [$node_name => $childAttrTree];
            } else {   # 不含子节点
                # 通过node_type类型查找
                $nodeTypeData = $this->getNodeData($nodeData, $baseNodeTypeModel, 'type', $fileId);
                # 通过类型查找不产生新的节点，只是规定节点类型而已，节点信息还是原来的，把信息拿过来
                $nodeTypeData['node_name'] = $nodeData['node_name'];
                # 标识信息来源，用于查找子节点
                $nodeTypeData['from'] = 'base';
                return $this->getAllChildAttr($nodeTypeData, $fileId);
            }
        } else {      // 最后一级
            $childAttrTree[$node_name] = 1;
            return $childAttrTree;
        }
    }

    /**
     * 根据现有信息往下级查找
     * @param $nodeData
     * @param $model    模型注入
     * @param $type     类型  ref,type,childNode
     * @param $field
     * @return mixed
     */
    public function getNodeData($nodeData, $model, $type, $fileId, $field = null)
    {
        $where = $this->getNodeDataWhere($nodeData, $type, $fileId);
        if ($field === 'allField') {
            $fields = '*';
        } else if ($model instanceof AmazonBaseNodeTypeModel) {
            $fields = 'id,node_name,node_type,ref,has_child_node,is_display_type';
        } else {
            $fields = 'id,node_name,node_type,ref,has_child_node,is_display_type,file_id';
        }
        $method = $type === 'childNode' ? 'select' : 'find';
        $nodeData = $model->where($where)
            ->field($fields)
            ->$method();
        return $nodeData;
    }

    /**
     * 获取查找下级元素的筛选条件
     * @param $nodeData
     * @param $type
     * @return array
     */
    public function getNodeDataWhere($nodeData, $type, $fileId = null)
    {
        if ($fileId) {
            $cateIdWhere = ['in', '0,' . $fileId];
        } else {
            $cateIdWhere = 0;
        }
        $where = [];
        if ($type === 'ref') {
            $where['node_name'] = $nodeData['ref'];
            $where['cate_id'] = $cateIdWhere;
        } else if ($type === 'type') {
            $where['node_name'] = $nodeData['node_type'];
            $where['cate_id'] = $cateIdWhere;
        } else {
            $where['p_node_id'] = $nodeData['id'];
        }
        return $where;
    }

    /**
     * 加缓存
     * @param $data
     * @param null $key
     * @param int $expire
     */
    public function setCacheData($data, $key = null, $expire = 864000)
    {
        if (is_null($key)) {
            $key = key($data);
            $data = current($data);
        }
        S(['type' => 'redis']);
        S($key, json_encode($data), $expire);
    }

    /**
     * 查缓存
     * @param $key
     * @return mixed
     */
    public function getCacheData($key)
    {
        S(['type' => 'redis']);
        return json_decode(S($key), true);
    }

}