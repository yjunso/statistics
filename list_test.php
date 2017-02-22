<?php
$location = "�ű����";
include "../_header.php";
@include "../../conf/config.pay.php";
include "../../lib/page.class.php";
@include "../../conf/phone.php";


if (get_magic_quotes_gpc()) {
	stripslashes_all($_POST);
	stripslashes_all($_GET);
}

// $_GET���� �޴� ��� �� ����
$search=array(
	'first'=>(string)$_GET['first'], // ó�� ���ȭ���� ���������� ���� ��
	'regdt_start'=>(string)$_GET['regdt'][0], // ó������ ����
	'regdt_end'=>(string)$_GET['regdt'][1], // ó������ ��
	'dtkind'=>(string)($_GET['dtkind'] ? $_GET['dtkind'] : 'orddt'), // ó������ ����
	'mode'=>(string)$_GET['mode'], // ��� ���� ( �ֹ��Ϸ� ���� , �ֹ�ó���帧���� ���� )
	'sword'=>trim((string)$_GET['sword']), // �˻���
	'skey'=>($_GET['skey'] ? (string)$_GET['skey'] : 'all'), // �˻��ʵ�
	'sgword'=>trim((string)$_GET['sgword']), // ��ǰ �˻���
	'sgkey'=>(string)$_GET['sgkey'], // ��ǰ �˻��ʵ�
	'step'=>(array)$_GET['step'], // �ֹ�����
	'step2'=>(array)$_GET['step2'], // �ֹ�����
	'settlekind'=>(string)$_GET['settlekind'], // �������
	'escrowyn'=>(string)$_GET['escrowyn'], // ����ũ��
	'eggyn'=>(string)$_GET['eggyn'], // ���ں�������
	'chk_inflow'=>(array)$_GET['chk_inflow'], // ����ó�ֹ�
	'couponyn'=>(string)$_GET['couponyn'], // �������
	'cashreceipt'=>(string)$_GET['cashreceipt'], // ���ݿ�����
	'cbyn'=>(string)$_GET['cbyn'], // OKCashbag����
	'aboutcoupon'=>(string)$_GET['aboutcoupon'], // ��ٿ�����
	'mobilepay'=>(string)$_GET['mobilepay'], // ����ϼ�
	'todaygoods'=>(string)$_GET['todaygoods'], // �����̼�
	'regdt_time_start'=>$_GET['regdt_time'] ? (int)$_GET['regdt_time'][0] : -1, // ó������ �ð���
	'regdt_time_end'=>$_GET['regdt_time'] ? (int)$_GET['regdt_time'][1] : -1, // ó������ �ð���
	'sugi'=>(string)$_GET['sugi'],
	'itemcondition'=>$_GET['itemcondition'],
	's_prn_settleprice'=>(string)$_GET['prn_settleprice'][0], // �����ݾ� ����
	'e_prn_settleprice'=>(string)$_GET['prn_settleprice'][1], // �����ݾ� ��
	'payco'=>(string)$_GET['payco'], // ������
);
$page = (int)$_GET['page'] ? (int)$_GET['page'] : 1;

// first ���ڰ��� ���� ó������ �⺻�� ����
if($search['first']) {
	if(!$cfg['orderPeriod']) $cfg['orderPeriod']=0;
	$search['regdt_start'] = date('Ymd',strtotime('-'.$cfg['orderPeriod'].' day'));
	$search['regdt_end'] = date('Ymd');
}
$search['first']=0;

// ��������
if(!in_array($search['dtkind'],array('orddt','cdt','ddt','confirmdt'))) { exit; }
if(!in_array($search['skey'],array('all','ordno','nameOrder','nameReceiver','bankSender','m_id','mobileOrder'))) { exit; }
if(!in_array($search['sgkey'],array('','goodsnm','brandnm','maker'))) { exit; }
foreach($search['step'] as $k=>$v) { $search['step'][$k]=(int)$v; }
foreach($search['step2'] as $k=>$v) { $search['step2'][$k]=(int)$v; }

// �������� ���� �˻����� �����
$isOrderItemSearch=false;
$arWhere = array();
// ��������
if($search['sugi']) {
	if($search['sugi'] == "Y") $arWhere[] = "o.inflow = 'sugi'";
	elseif($search['sugi'] == "N") $arWhere[] = "o.inflow != 'sugi'";
}
if($search['regdt_start']) {
	if(!$search['regdt_end']) $search['regdt_end'] = date('Ymd');

	$tmp_start = substr($search['regdt_start'],0,4).'-'.substr($search['regdt_start'],4,2).'-'.substr($search['regdt_start'],6,2);
	$tmp_end = substr($search['regdt_end'],0,4).'-'.substr($search['regdt_end'],4,2).'-'.substr($search['regdt_end'],6,2);

	if ($search['regdt_time_start'] !== -1 && $search['regdt_time_end'] !== -1) {
		$tmp_start .= ' '.sprintf('%02d',$search['regdt_time_start']).':00:00';
		$tmp_end .= ' '.sprintf('%02d',$search['regdt_time_end']).':59:59';
	}
	else {
		$tmp_start .= ' 00:00:00';
		$tmp_end .= ' 23:59:59';
	}
	switch($search['dtkind']) {
		case 'orddt': $arWhere[] = $db->_query_print('o.orddt between [s] and [s]',$tmp_start,$tmp_end); break;
		case 'cdt': $arWhere[] = $db->_query_print('o.cdt between [s] and [s]',$tmp_start,$tmp_end); break;
		case 'ddt': $arWhere[] = $db->_query_print('o.ddt between [s] and [s]',$tmp_start,$tmp_end); break;
		case 'confirmdt': $arWhere[] = $db->_query_print('o.confirmdt between [s] and [s]',$tmp_start,$tmp_end); break;
	}
}
if($search['settlekind']) {
	$arWhere[] = $db->_query_print('o.settlekind = [s]',$search['settlekind']);
}
if(count($search['step']) || count($search['step2'])) {
	$subWhere = array();
	if(count($search['step'])) {
		$subWhere[] = '(o.step in ("'.implode('","', $search['step']).'") and o.step2="0")';
	}
	if(count($search['step2'])) {
		foreach($search['step2'] as $k=>$v) {
			switch($v) {
				case 1: $subWhere[] = '(o.step=0 and o.step2 between 1 and 49)'; break;
				case 2: $subWhere[] = '(o.step in (1,2) and o.step2!=0) OR (o.cyn="r" and o.step2="44" and o.dyn!="e")'; break;
				case 3: $subWhere[] = '(o.step in (3,4) and o.step2!=0)'; break;
				case 60 : $subWhere[] = "(oi.dyn='e' and oi.cyn='e')"; $isOrderItemSearch=true; break; //��ȯ�Ϸ�
				case 61 : $subWhere[] = "o.oldordno != ''";break; //���ֹ�
				default : $subWhere[] = "o.step2 = '$v'";
			}
		}
	}
	if(count($subWhere)) {
		$arWhere[] = '('.implode(' or ',$subWhere).')';
	}
}
if($search['sword'] && $search['skey']) {
	$es_sword = $db->_escape($search['sword']);
	switch($search['skey']) {
		case 'all':
			$arWhere[] = "(
				o.ordno = '{$es_sword}' or
				o.nameOrder like '%{$es_sword}%' or
				o.nameReceiver like '%{$es_sword}%' or
				o.bankSender like '%{$es_sword}%' or
				m.m_id = '{$es_sword}' or
				o.mobileOrder like '%{$es_sword}%'
			)"; break;
		case 'ordno': $arWhere[] = "o.ordno = '{$es_sword}'"; break;
		case 'nameOrder': $arWhere[] = "o.nameOrder like '%{$es_sword}%'"; break;
		case 'nameReceiver': $arWhere[] = "o.nameReceiver like '%{$es_sword}%'"; break;
		case 'bankSender': $arWhere[] = "o.bankSender like '%{$es_sword}%'"; break;
		case 'm_id': $arWhere[] = "m.m_id = '{$es_sword}'"; break;
		case 'mobileOrder': $arWhere[] = "o.mobileOrder like '%{$es_sword}%'"; break;
	}
}
if($search['sgword'] && $search['sgkey']) {
	$es_sgword = $db->_escape($search['sgword']);
	switch($search['sgkey']) {
		case 'goodsnm': $arWhere[] = "oi.goodsnm like '%{$es_sgword}%'"; break;
		case 'brandnm': $arWhere[] = "oi.brandnm like '%{$es_sgword}%'"; break;
		case 'maker': $arWhere[] = "oi.maker like '%{$es_sgword}%'"; break;
	}
	$isOrderItemSearch=true;
}

//�ֹ��ݾ� �˻�
if ($search['s_prn_settleprice'] != '' && $search['e_prn_settleprice'] != '')			$arWhere[] = "o.prn_settleprice between ".$search['s_prn_settleprice']." and ".$search['e_prn_settleprice'];
else if ($search['s_prn_settleprice'] != '' &&  $search['e_prn_settleprice'] == '')		$arWhere[] = "o.prn_settleprice >= ".$search['s_prn_settleprice'];
else if ($search['s_prn_settleprice'] == '' && $search['e_prn_settleprice'] != '')		$arWhere[] = "o.prn_settleprice <= ".$search['e_prn_settleprice'];

if(count($search['chk_inflow'])) {
	$es_inflow = array();
	foreach($search['chk_inflow'] as $v) {
		if($v == 'naver_price') {
			$es_inflow[] = '"naver_elec"';
			$es_inflow[] = '"naver_bea"';
			$es_inflow[] = '"naver_milk"';
		}
		else if($v == 'plus_cheese'){
			$arWhere[] = 'o.pCheeseOrdNo<>\'\'';
		}
		else {
			$es_inflow[] = '"'.$db->_escape($v).'"';
		}
	}
	if(!empty($es_inflow)){
		$arWhere[] = 'o.inflow in ('.implode(',',$es_inflow).')';
	}
}
if($search['cbyn']=='Y') {
	$arWhere[] = 'o.cbyn = "Y"';
}
if($search['aboutcoupon']=='1') {
	$arWhere[] = 'o.about_coupon_flag = "Y"';
}
if($search['escrowyn']) {
	$arWhere[] = $db->_query_print('o.escrowyn = [s]',$search['escrowyn']);
}
if($search['eggyn']) {
	$arWhere[] = $db->_query_print('o.eggyn = [s]',$search['eggyn']);
}
if($search['mobilepay']) {
	$arWhere[] = $db->_query_print('o.mobilepay = [s]',$search['mobilepay']);
}
if ($search['todaygoods']) {
	$arWhere[] = $db->_query_print('exists(SELECT * FROM '.GD_ORDER_ITEM.' AS oi JOIN '.GD_GOODS.' AS g ON oi.goodsno=g.goodsno WHERE oi.ordno=o.ordno AND g.todaygoods=[s])',$search[todaygoods]);
}
if($search['cashreceipt']) {
	$arWhere[] = 'o.cashreceipt != ""';
}
if($search['payco']) {
	$arWhere[] = 'o.settleInflow = "payco"';
}
if($search['couponyn']) {
	$arWhere[] = 'co.ordno is not null';
	$join_GD_COUPON_ORDER='left join '.GD_COUPON_ORDER.' as co on o.ordno=co.ordno';
}
else {
	$join_GD_COUPON_ORDER='';
}

// gd_order_item ���� �˻������� �߻��ϴ� ��� ��ǰ������ ��ǰ����üũ�� ������ ó��
if($isOrderItemSearch) {
	$select_count_item = '(select count(*) from '.GD_ORDER_ITEM.' as s_oi where s_oi.ordno=o.ordno) as count_item';
	$select_count_dv_item = '(select count(*) from '.GD_ORDER_ITEM.' as s_oi where s_oi.ordno=o.ordno and s_oi.dvcode!="" and s_oi.dvno!="") as count_dv_item';
}
else {
	$select_count_item = 'count(oi.ordno) as count_item';
	$select_count_dv_item = 'sum(oi.dvcode != "" and oi.dvno != "") as count_dv_item';
}

if(count($arWhere)) {
	$strWhere = 'where '.implode(' and ',$arWhere);
}
echo $strWhere;
// ���� ����
/* ++��ǰ��ȣ �� �׷��� �ϱ� ���� array */
$goodsList=array();
/* --��ǰ��ȣ �� �׷��� �ϱ� ���� array */
$orderList=array();
$orderGroupNameMap=array();

$query = '
	select
		o.ordno as ordno,
		o.nameOrder as nameOrder,
		o.nameReceiver as nameReceiver,
		o.settlekind as settlekind,
		o.step as step,
		o.step2 as step2,
		o.orddt as orddt,
		o.dyn as dyn,
		o.escrowyn as escrowyn,
		o.eggyn as eggyn,
		o.inflow as inflow,
		o.deliverycode as deliverycode,
		o.cashreceipt as cashreceipt,
		o.cbyn as cbyn,
		o.oldordno as oldordno,
		o.prn_settleprice as prn_settleprice,
		o.pCheeseOrdNo,
		o.inflow as inflow,
		m.m_id as m_id,
		m.m_no as m_no,
		m.level as level,
		o.settleInflow as settleInflow,
		'.$select_count_item.',
		'.$select_count_dv_item.',
		oi.goodsnm as goodsnm,
		oi.goodsno as goodsno
	from
		'.GD_ORDER.' as o
		left join '.GD_ORDER_ITEM.' as oi on o.ordno=oi.ordno
		left join '.GD_MEMBER.' as m on o.m_no = m.m_no
		'.$join_GD_COUPON_ORDER.'
	'.$strWhere.'
	group by o.ordno
';

if($search['mode']=='group') {
	echo "<p>------group---------</p>";
	$result = $db->_select($query);

	// �׷캰�� �ֹ��� �Ҵ�
	foreach($result as $v) {
		$orderGroupKey = $v['step2']*10+($v['step'] === '1' || ($v['step'] === '2' && $v['step2'] > 40) ? 1 : $v['step']);
		$orderGroupNameMap[$orderGroupKey] = getStepMsg($v['step'],$v['step2']);
		$orderList[$orderGroupKey][] = $v;
	}
	ksort($orderList);

	// ����
	foreach($orderList as $orderGroupKey=>$eachOrderGroup) {
		$sortAssistDyn=$sortAssistOrdno=array();
		foreach ($eachOrderGroup as $k => $v) {
			$sortAssistDyn[$k]  = $v['dyn'];
			$sortAssistOrdno[$k] = $v['ordno'];
			$orderList[$orderGroupKey][$k]['stepMsg'] = getStepMsg($v['step'],$v['step2'],$v['ordno']);
		}
		array_multisort($sortAssistDyn,SORT_ASC,$sortAssistOrdno,SORT_DESC,$orderList[$orderGroupKey]);

		$i=0;
		foreach ($eachOrderGroup as $k => $v) {
			$orderList[$orderGroupKey][$k]['_rno'] = count($eachOrderGroup)-($i++);
		}
	}
}
else if($search['mode']=='name') {
	echo "<p>------name---------</p>";
	$result = $db->_select($query);
	$goodsListIdx = 0;
	$srchRst=0;
	$Rstcnt=0;
	
	// �׷캰�� �ֹ��� �Ҵ�
	foreach($result as $v) {
		$srchRst=array_search($v['goodsno'], $goodsList);
		if (!$srchRst){
			$srchRst=$goodsListIdx;
		    $goodsList[$goodsListIdx] = $v['goodsno'];
		    $goodsListIdx++;
		}
		$orderGroupKey = $srchRst;
		$orderGroupNameMap[$orderGroupKey] = $v['goodsnm'];
		$orderList[$orderGroupKey][] = $v;
		$Rstcnt++;
	}
	echo "<p>-----" . $goodsListIdx . "---" . $Rstcnt . "--</p>";
	ksort($orderList);
	// ����
	foreach($orderList as $orderGroupKey=>$eachOrderGroup) {
		$sortAssistDyn=$sortAssistOrdno=array();
		foreach ($eachOrderGroup as $k => $v) {
			$sortAssistDyn[$k]  = $v['dyn'];
			$sortAssistOrdno[$k] = $v['ordno'];
			$orderList[$orderGroupKey][$k]['stepMsg'] = getStepMsg($v['step'],$v['step2'],$v['ordno']);
		}
		array_multisort($sortAssistDyn,SORT_ASC,$sortAssistOrdno,SORT_DESC,$orderList[$orderGroupKey]);

		$i=0;
		foreach ($eachOrderGroup as $k => $v) {
			$orderList[$orderGroupKey][$k]['_rno'] = count($eachOrderGroup)-($i++);
		}
	}
}
else {
	echo "<p>------else---------</p>";
	if(!$cfg['orderPageNum']) $cfg['orderPageNum'] = 15;

	//$query = $query.' order by o.ordno desc';
	$query = $query.' order by goodsnm desc';
	
	$result = $db->_select_page($cfg['orderPageNum'],$page,$query);

	$orderList[9999]=array();
	foreach($result['record'] as $v) {
		$v['stepMsg']=getStepMsg($v['step'],$v['step2'],$v['ordno']);
		$orderList[9999][] = $v;
	}
	$pageNavi = $result['page'];
}

### �׷�� ��������
$r_grp = array();
$garr = member_grp();
foreach( $garr as $v ) $r_grp[$v['level']] = $v['grpnm'];
?>

<script type="text/javascript">
/**
* ���λ��� Ȱ��ȭ
*/
function iciSelect(obj) {
	var row = obj.parentNode.parentNode;
	row.style.background = (obj.checked) ? "#F9FFA1" : row.getAttribute('bg');
	if($('tr_'+obj.value)) {
		$('tr_'+obj.value).style.background=row.style.background;
	}
}

/**
* ��ü����
*/
var chkBoxAll_flag=true;
function chkBoxAll() {
	$$(".chk_ordno").each(function(item){
		if(item.disabled==true) return;
		item.checked=chkBoxAll_flag;
		iciSelect(item);
	});
	chkBoxAll_flag=!chkBoxAll_flag;
}
/**
* �׷켱��
*/
var chkBoxGroup_flag=true;
function chkBoxGroup(k) {
	$$(".chk_ordno_"+k).each(function(item){
		if(item.disabled==true) return;
		item.checked=chkBoxGroup_flag;
		iciSelect(item);
	});
	chkBoxGroup_flag=!chkBoxGroup_flag;
}
/**
* �ֹ����º������
*/
function processOrder() {
	f = $('frmList');
	var selCase = f.select('select[name=case]')[0];
	var isGodoChk=false;

	if(!selCase.value) {
		alert('�ֹ����°��� �������ּ���');
		return;
	}

	f.select("input[type=checkbox]").each(function(item){
		var re = new RegExp('^chk');
		if(re.test(item.name) && item.checked) {
			isGodoChk=true;
		}
	});

	// ���̹�üũ�ƿ�
	if (typeof(processCheckoutOrder) == 'function') {
		if (processCheckoutOrder(f, selCase, isGodoChk) === false) {
			return;
		}
	}

	if(isGodoChk) {
		f.submit();
	}
	else {
		alert('�ֹ����� �������ּ���');
	}
}

/**
* �������� �ٿ�ε�
*/
function dnXls(mode)
{
	var fm = document.frmDnXls;
	var o = document.getElementsByName('itemcondition');
	var ic_value = "";
	if (o.length == 3) {
		if (o[0].checked == true) ic_value = "";
		if (o[1].checked == true) ic_value = "N";
		if (o[2].checked == true) ic_value = "A";
	}
	fm.mode.value = mode;
	fm.xls_itemcondition.value = ic_value;
	fm.target = "ifrmHidden";
	fm.action = "dnXls.php";
	fm.submit();
}
</script>

<div class="title title_top" style="position:relative;padding-bottom:15px">��ǰ�� �ֹ� ��Ȳ<span>��ǰ�� �ֹ� ��Ȳ�� Ȯ���մϴ�</span>
<a href="javascript:manual('<?=$guideUrl?>board/view.php?id=order&no=2')"><img src="../img/btn_q.gif" border="0" hspace="2" align="absmiddle"/></a>

</div>

<form>
<input type="hidden" name="mode" value="<?=$search['mode']?>"/>

<table class="tb">
<col class="cellC"><col class="cellL" style="width:300px">
<col class="cellC"><col class="cellL">
<tr>
	<td><span class="small1">�ֹ��˻� (����)</span></td>
	<td>
	<select name="skey">
	<option value="all"> = ���հ˻� = </option>
	<option value="ordno" <?=frmSelected($search['skey'],'ordno');?>> �ֹ���ȣ</option>
	<option value="nameOrder" <?=frmSelected($search['skey'],'nameOrder');?>> �ֹ��ڸ�</option>
	<option value="nameReceiver" <?=frmSelected($search['skey'],'nameReceiver');?>> �����ڸ�</option>
	<option value="bankSender" <?=frmSelected($search['skey'],'bankSender');?>> �Ա��ڸ�</option>
	<option value="m_id" <?=frmSelected($search['skey'],'m_id');?>> ���̵�</option>
	<option value="mobileOrder" <?=frmSelected($search['skey'],'mobileOrder');?>> �ֹ��� �ڵ�����ȣ</option>
	</select>
	<input type="text" name="sword" value="<?=htmlspecialchars($search['sword'])?>" class="line"/>
	</td>
	<td><span class="small1">��ǰ�˻� (����)</span></td>
	<td>
	<select name="sgkey">
	<option value="goodsnm" <?=frmSelected($search['sgkey'],'goodsnm');?>> ��ǰ��</option>
	<option value="brandnm" <?=frmSelected($search['sgkey'],'brandnm');?>> �귣��</option>
	<option value="maker" <?=frmSelected($search['sgkey'],'maker');?>> ������</option>
	</select>
	<input type=text name="sgword" value="<?=htmlspecialchars($search['sgword'])?>" class="line"/>
	</td>
</tr>
<tr>
	<td><span class="small1">��������</span></td>
	<td class="noline"><span class="small1" style="color:#5C5C5C;">
	<input type="radio" name="sugi" id="sugi_all" value="" <?=frmChecked('',$search['sugi'])?> /><label for="sugi_all">��ü</label>
	<input type="radio" name="sugi" id="sugi_N" value="N" <?=frmChecked('N',$search['sugi'])?> /><label for="sugi_N">�¶�������</label>
	<input type="radio" name="sugi" id="sugi_Y" value="Y" <?=frmChecked('Y',$search['sugi'])?> /><label for="sugi_Y">��������</label>
	</span></td>
	<td><span class="small1">�����ݾ�</span></td>
	<td>
	<input type="text" name="prn_settleprice[]" value="<?=$search['s_prn_settleprice']?>" size="10" onkeydown="onlynumber();" class="rline" />�� ~
	<input type="text" name="prn_settleprice[]" value="<?=$search['e_prn_settleprice']?>" size="10" onkeydown="onlynumber();" class="rline" />��
	</td>
</tr>

<tr>
	<td><span class="small1">�ֹ�����</span></td>
	<td colspan="3" class="noline">
	<? foreach ($r_step as $k=>$v){ ?>
	<div style="float:left; padding-right:10px; color:#5C5C5C;"><input type="checkbox" name="step[]" value="<?=$k?>" <?=(in_array($k,$search['step'])?'checked':'')?>><span class="small1"><?=$v?></span></input></div>
	<? } ?>
	<div style="float:left; padding-right:10px; color:#5C5C5C;"><input type="checkbox" name="step2[]" value="1" <?=(in_array(1,$search['step2'])?'checked':'')?>><span class="small1">�ֹ����</span></input></div>
	<div style="clear:both;"></div>
	<div style="float:left; padding-right:10px; color:#5C5C5C;"><input type="checkbox" name="step2[]" value="2" <?=(in_array(2,$search['step2'])?'checked':'')?>><span class="small1">ȯ�Ұ���</span></input></div>
	<div style="float:left; padding-right:10px; color:#5C5C5C;"><input type="checkbox" name="step2[]" value="3" <?=(in_array(3,$search['step2'])?'checked':'')?>><span class="small1">��ǰ����</span></input></div>
	<div style="float:left; padding-right:10px; color:#5C5C5C;"><input type="checkbox" name="step2[]" value="60" <?=(in_array(60,$search['step2'])?'checked':'')?>><span class="small1">��ȯ�Ϸ�</span></input></div>
	<div style="float:left; padding-right:10px; color:#5C5C5C;"><input type="checkbox" name="step2[]" value="61" <?=(in_array(61,$search['step2'])?'checked':'')?>><span class="small1">���ֹ�</span></input></div>
	<div style="float:left; padding-right:10px; color:#5C5C5C;"><input type="checkbox" name="step2[]" value="50" <?=(in_array(50,$search['step2'])?'checked':'')?>><span class="small1">�����õ�</span></input></div>
	<div style="float:left; padding-right:10px; color:#5C5C5C;"><input type="checkbox" name="step2[]" value="54" <?=(in_array(54,$search['step2'])?'checked':'')?>><span class="small1">��������</span></input></div>
	<div style="float:left; padding-right:10px; color:#5C5C5C;"><input type="checkbox" name="step2[]" value="51" <?=(in_array(51,$search['step2'])?'checked':'')?>><span class="small1">PGȮ�ο��</span></input></div>
	</td>
</tr>
<tr>
	<td><span class="small1">ó������</span></td>
	<td colspan="3">
	<span class="noline small1" style="color:5C5C5C; margin-right:20px;">
	<input type="radio" name="dtkind" value="orddt" <?=frmChecked($search['dtkind'],'orddt')?>>�ֹ���</input>
	<input type="radio" name="dtkind" value="cdt" <?=frmChecked($search['dtkind'],'cdt')?>>����Ȯ����</input>
	<input type="radio" name="dtkind" value="ddt" <?=frmChecked($search['dtkind'],'ddt')?>>�����</input>
	<input type="radio" name="dtkind" value="confirmdt" <?=frmChecked($search['dtkind'],'confirmdt')?>>��ۿϷ���</input>
	</span>

	<input type="text" name="regdt[]" value="<?=$search['regdt_start']?>" onclick="calendar(event)" size="12" class="line"/>
	<select name="regdt_time[]">
	<option value="-1">---</option>
	<? for ($i=0;$i<24;$i++) {?>
	<option value="<?=$i?>" <?=($search['regdt_time_start'] === $i ? 'selected' : '')?>><?=sprintf('%02d',$i)?>��</option>
	<? } ?>
	</select>
	-
	<input type="text" name="regdt[]" value="<?=$search['regdt_end']?>" onclick="calendar(event)" size="12" class="line"/>
	<select name="regdt_time[]">
	<option value="-1">---</option>
	<? for ($i=0;$i<24;$i++) {?>
	<option value="<?=$i?>" <?=($search['regdt_time_end'] === $i ? 'selected' : '')?>><?=sprintf('%02d',$i)?>��</option>
	<? } ?>
	</select>

	<a href="javascript:setDate('regdt[]',<?=date("Ymd")?>,<?=date("Ymd")?>)"><img src="../img/sicon_today.gif" align="absmiddle"/></a>
	<a href="javascript:setDate('regdt[]',<?=date("Ymd",strtotime("-7 day"))?>,<?=date("Ymd")?>)"><img src="../img/sicon_week.gif" align="absmiddle"/></a>
	<a href="javascript:setDate('regdt[]',<?=date("Ymd",strtotime("-15 day"))?>,<?=date("Ymd")?>)"><img src="../img/sicon_twoweek.gif" align="absmiddle"/></a>
	<a href="javascript:setDate('regdt[]',<?=date("Ymd",strtotime("-1 month"))?>,<?=date("Ymd")?>)"><img src="../img/sicon_month.gif" align="absmiddle"/></a>
	<a href="javascript:setDate('regdt[]',<?=date("Ymd",strtotime("-2 month"))?>,<?=date("Ymd")?>)"><img src="../img/sicon_twomonth.gif" align="absmiddle"/></a>
	<a href="javascript:setDate('regdt[]')"><img src="../img/sicon_all.gif" align="absmiddle"/></a>
	</td>
</tr>
<!--
<tr>
	<td><span class="small1">�������</span></td>
	<td colspan="3" class="noline"><span class="small1" style="color:#5C5C5C;">
	<input type="radio" name="settlekind" value="" <?=frmChecked('',$search['settlekind'])?>>��ü</input>
	<input type="radio" name="settlekind" value="a" <?=frmChecked('a',$search['settlekind'])?>>������</input>
	<input type="radio" name="settlekind" value="c" <?=frmChecked('c',$search['settlekind'])?>>�ſ�ī��</input>
	<input type="radio" name="settlekind" value="o" <?=frmChecked('o',$search['settlekind'])?>>������ü</input>
	<input type="radio" name="settlekind" value="v" <?=frmChecked('v',$search['settlekind'])?>>�������</input>
	<input type="radio" name="settlekind" value="h" <?=frmChecked('h',$search['settlekind'])?>>�ڵ���</input>
	<? if ($cfg['settlePg'] == "inipay") { ?>
	<input type="radio" name="settlekind" value="y" <?=frmChecked('y',$search['settlekind'])?>>��������</input>
	<? } ?>
	<input type="radio" name="settlekind" value="d" <?=frmChecked('d',$search['settlekind'])?>>��������</input><br>
	<input type="checkbox" name="payco" value="1" <?=frmChecked('1',$search['payco'])?>><img src="../img/icon_payco.gif"/>������</input>
	<input type="checkbox" name="couponyn" value="1" <?=frmChecked('1',$search['couponyn'])?>>�������</input>
	<input type="checkbox" name="cashreceipt" value="1" <?=frmChecked('1',$search['cashreceipt'])?>>���ݿ����� <img src="../img/icon_cash_receipt.gif"/></input>
	<input type="radio" name="settlekind" value="p" <?=frmChecked('p',$search['settlekind'])?>>����Ʈ</input>
	<input type="checkbox" name="cbyn" value="Y" <?=frmChecked('p',$search['cbyn'])?>><img src="../img/icon_okcashbag.gif" align="absmiddle"/>OKCashBag����</input>
	<input type="checkbox" name="aboutcoupon" value="1" <?=frmChecked('1',$search['aboutcoupon'])?>>��ٿ�����</input>
	</span>
	</td>
</tr>
<tr>
	<td><span class="small1">����ϼ�</span></td>
	<td colspan="3" class="noline"><span class="small1" style="color:#5C5C5C;">
	<input type="radio" name="mobilepay" value="" <?=frmChecked('',$search['mobilepay'])?>>��ü</input>
	<input type="radio" name="mobilepay" value="n" <?=frmChecked('n',$search['mobilepay'])?>>�Ϲݰ���</input>
	<input type="radio" name="mobilepay" value="y" <?=frmChecked('y',$search['mobilepay'])?>>����ϼ�����</input>
	</td>
</tr>
<tr>
	<td><font class="small1">�����̼�</td>
	<td colspan="3" class="noline"><font class="small1" color="5C5C5C">
	<input type="radio" name="todaygoods" value="" <?=frmChecked('',$search['todaygoods'])?>>��ü
	<input type="radio" name="todaygoods" value="n" <?=frmChecked('n',$search['todaygoods'])?>>�Ϲݻ�ǰ
	<input type="radio" name="todaygoods" value="y" <?=frmChecked('y',$search['todaygoods'])?>>�����̼���ǰ
	</td>
</tr>
<tr>
	<td><span class="small1">����ũ��</span></td>
	<td class="noline"><span class="small1" style="color:#5C5C5C;">
	<input type="radio" name="escrowyn" value="" <?=frmChecked('',$search['escrowyn'])?>>��ü</input>
	<input type="radio" name="escrowyn" value="n" <?=frmChecked('n',$search['escrowyn'])?>>�Ϲݰ���</input>
	<input type="radio" name="escrowyn" value="y" <?=frmChecked('y',$search['escrowyn'])?>>����ũ�� <img src="../img/btn_escrow.gif" align="absmiddle"/></input>
	</td>
	<td><span class="small1">���ں�������</span> <a href="../basic/egg.intro.php"><img src="../img/btn_question.gif"/></a></td>
	<td class="noline"><span class="small1" style="color:#5C5C5C;">
	<input type="radio" name="eggyn" value="" <?=frmChecked('',$search['eggyn'])?>>��ü</input>
	<input type="radio" name="eggyn" value="n" <?=frmChecked('n',$search['eggyn'])?>>�̹߱�</input>
	<input type="radio" name="eggyn" value="f" <?=frmChecked('f',$search['eggyn'])?>>�߱޽���</input>
	<input type="radio" name="eggyn" value="y" <?=frmChecked('y',$search['eggyn'])?>>�߱޿Ϸ� <img src="../img/icon_guar_order.gif"/></input>
	</td>
</tr>
<tr>
	<td><span class="small1">����ó�ֹ�</span> <a href="../naver/naver.php"><img src="../img/btn_question.gif"/></a></td>
	<td colspan="3" class="noline"><span class="small1" style="color:#5C5C5C;">
	<input type="checkbox" name="chk_inflow[]" value="naver" <?=(in_array('naver',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_naver.gif" align="absmiddle"/> ���̹����ļ���</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="naver_pchs_040901" <?=(in_array('naver_pchs_040901',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_naver_pchs_040901.gif" align="absmiddle"/> ���̹����ļ�����õ����</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="yahoo_fss" <?=(in_array('yahoo_fss',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_yahoo_fss.gif" align="absmiddle"/> �����мǼ�ȣ</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="interpark" <?=(in_array('interpark',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_interpark.gif" align="absmiddle"/> ������ũ���÷���</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="openstyle" <?=(in_array('openstyle',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_interpark.gif" align="absmiddle"/> ������ũ���½�Ÿ��</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="openstyleOutlink" <?=(in_array('openstyleOutlink',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_interpark.gif" align="absmiddle"/> ������ũ���½�Ÿ�Ͼƿ���ũ</input><br>
	<input type="checkbox" name="chk_inflow[]" value="naver_price" <?=(in_array('naver_price',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_naver_price.gif" align="absmiddle"/> ���̹����ݺ�</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="danawa" <?=(in_array('danawa',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_danawa.gif" align="absmiddle"/> �ٳ���</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="mm" <?=(in_array('mm',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_mm.gif" align="absmiddle"/> ���̸���</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="bb" <?=(in_array('bb',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_bb.gif" align="absmiddle"/> ����Ʈ���̾�</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="omi" <?=(in_array('omi',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_omi.gif" align="absmiddle"/> ����</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="enuri" <?=(in_array('enuri',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_enuri.gif" align="absmiddle"/> ������</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="yahoo" <?=(in_array('yahoo',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_yahoo.gif" align="absmiddle"/> ���İ��ݺ�</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="yahooysp" <?=(in_array('yahooysp',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_yahooysp.gif" align="absmiddle"/> ����������</input><br />
	<input type="checkbox" name="chk_inflow[]" value="auctionos" <?=(in_array('auctionos',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_auctionos.gif" align="absmiddle"/> ���Ǿ�ٿ�</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="daumCpc" <?=(in_array('daumCpc',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_daumCpc.gif" align="absmiddle"/> ���������Ͽ�</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="cywordScrap" <?=(in_array('cywordScrap',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_cywordScrap.gif" align="absmiddle"/> ���̿��彺ũ��</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="naverCheckout" <?=(in_array('naverCheckout',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_naverCheckout.gif" align="absmiddle"/> ���̹�üũ�ƿ�</input>
	<input type="checkbox" name="chk_inflow[]" value="plus_cheese" <?=(in_array('plus_cheese',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_plus.gif" align="absmiddle"/> �÷���ġ��</input>
	<input type="checkbox" name="chk_inflow[]" value="auctionIpay" <?=(in_array('auctionIpay',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_auctionIpay.gif" align="absmiddle"/> ����iPay</input>
	</td>
</tr>
-->
</table>
<div class="button_top">
<input type="image" src="../img/btn_search2.gif"/>
</div>
</form>

<div style="padding-top:15px"></div>


<form name="frmList" method="post" action="<?=$sitelink->link('admin/order/indb.php','ssl');?>"  id="frmList">
<input type="hidden" name="mode" value="chgAll"/>

<table width="100%" cellpadding="0" cellspacing="0">
<tr>
<!--
	<td>�Ʒ����� ������ �ֹ�����
	<select name="case" required label="��������">
	<option value="">- �ֹ����� ����ó�� -</option>
	<option value="0">�ֹ����� ó��</option>
	<option value="1">�Ա�Ȯ�� ó��</option>
	<option value="2">����غ��� ó��</option>
	<option value="3">����� ó��</option>
	<option value="4">��ۿϷ� ó��</option>
	</select> �մϴ�. <span class="extext">(������ �ϴ� ������ư �� Ŭ��)</span>
	</td>
	-->
	<td align="right">
	<?php if ($search['mode']=="group"):?>
		<a href="?<?=getVars('page,mode')?>"><img src="../img/btn_orderdate_off.gif" align="absmiddle"/></a>
		<a href="?mode=name&<?=getVars('page,mode')?>"><img src="../img/btn_orderdate_off.gif" align="absmiddle"/></a>
		<img src="../img/btn_orderprocess_on.gif" align="absmiddle"/>
	<?php elseif ($search['mode']=="name"): ?>
		<a href="?<?=getVars('page,mode')?>"><img src="../img/btn_orderdate_off.gif" align="absmiddle"/></a>
		<img src="../img/btn_orderprocess_on.gif" align="absmiddle"/>
		<a href="?mode=group&<?=getVars('page,mode')?>"><img src="../img/btn_orderprocess_off.gif" align="absmiddle"/></a>
	<?php else: ?> 
		<img src="../img/btn_orderdate_on.gif" align="absmiddle"/>
		<a href="?mode=name&<?=getVars('page,mode')?>"><img src="../img/btn_orderdate_off.gif" align="absmiddle"/></a>
		<a href="?mode=group&<?=getVars('page,mode')?>"><img src="../img/btn_orderprocess_off.gif" align="absmiddle"/></a>
	<?php endif; ?>
	</td>
</tr>
<tr><td height="3"></td></tr>
</table>


<table width="100%" cellpadding="0" cellspacing="0" border="0">
<!--
<col width="25">
-->
<col width="30"><col width="100"><col width="160"><col><col width="95"><col width="50"><col width="50"><col><col width="55">
<tr><td class="rnd" colspan="20"></td></tr>
<tr class="rndbg">
<!--
	<th><a href="javascript:void(0)" onClick="chkBoxAll()" class=white>����</a></th>
-->
	<th>��ȣ</th>
	<th>�ֹ��Ͻ�</th>
	<th colspan="2">�ֹ���ȣ (�ֹ���ǰ)</th>
	<th>�ֹ���</th>
	<th>�޴º�</th>
	<th>����</th>
	<th>�ݾ�</th>
	<th colspan="6">ó������</th>
</tr>
<tr><td class="rnd" colspan="20"></td></tr>
<?php
$totalPrnSettlePrice=0;
$checkOutIndex=0;
foreach($orderList as $orderGroupKey => $eachOrderGroup):
	$groupPrnSettlePrice=0;

	if($orderGroupKey!=9999):
?>
<tr><td colspan="13" bgcolor="#E8E7E7" height="1"></td></tr>
<!--
<tr align="center">
	<td colspan="13" bgcolor="#f7f7f7" height="30" style="padding-left:15px">
	<b><img src="../img/icon_process.gif" align="absmiddle"/>
		<?=$orderGroupNameMap[$orderGroupKey]?>
	</b>
	</td>
</tr>
-->
<?php
	endif;

	foreach($eachOrderGroup as $eachOrder):
		if($eachOrder['count_item']>1) $goodsnm = $eachOrder['goodsnm'].' ��'.($eachOrder['count_item']-1).'��';
		else $goodsnm = $eachOrder['goodsnm'];

		$groupPrnSettlePrice+=$eachOrder['prn_settleprice'];
		$disabled = ($eachOrder['step2']) ? 'disabled' : '';
		$bgcolor = ($eachOrder['step2']) ? "#F0F4FF" : "#ffffff";

		if($eachOrder['_order_type']=='checkout'):

		$checkOutIndex++;
?>

<tr height="25" bgcolor="<?=$bgcolor?>" bg="<?=$bgcolor?>" align="center">
<!--
	<td class="noline">

		<?php if($eachOrder['ProductOrderStatus']=='PAYED' && $eachOrder['PlaceOrderStatus']=='NOT_YET' AND $eachOrder['ClaimStatus']=='') { ?>
		-- ���� Ȯ�� --
		<input type="checkbox" name="PlaceProductOrder_OrderID[<?=$checkOutIndex?>]" value="<?=$eachOrder['ordno']?>" class="chk_ordno {ProductOrderStatus:'<?=$eachOrder['ProductOrderStatus']?>',ClaimType:'<?=$eachOrder['ClaimType']?>',ClaimStatus:'<?=$eachOrder['ClaimStatus']?>',PlaceOrderStatus:'<?=$eachOrder['PlaceOrderStatus']?>'}" onclick="iciSelect(this)"/>

		<?php } elseif($eachOrder['ProductOrderStatus']=='PAYED' && $eachOrder['PlaceOrderStatus']=='OK' AND $eachOrder['ClaimStatus']=='') { ?>
		-- �߼� ó�� --
		<input type="checkbox" name="ShipProductOrder_OrderID[<?=$checkOutIndex?>]" value="<?=$eachOrder['ordno']?>" class="chk_ordno {ProductOrderStatus:'<?=$eachOrder['ProductOrderStatus']?>',ClaimType:'<?=$eachOrder['ClaimType']?>',ClaimStatus:'<?=$eachOrder['ClaimStatus']?>',PlaceOrderStatus:'<?=$eachOrder['PlaceOrderStatus']?>'}" onclick="iciSelect(this)"/>

		<?php } else { ?>
		-- �׿� ó�� �Ҽ� ���� --
		<input type="checkbox" class="chk_ordno" disabled/>
		<?php } ?>

		<input type="hidden" name="ProductOrderIDList[<?=$checkOutIndex?>]" value="<?=$eachOrder['ProductOrderIDList']?>" />
	</td>
	-->
	<td><span class="ver8" style="color:#005B00"><?=$eachOrder['_rno']?></span></td>
	<td><span class="ver81" style="color:#005B00"><?=substr($eachOrder['orddt'],0,-3)?></span></td>
	<td align="center"><a href="checkout.view.php?OrderID=<?=$eachOrder['ordno']?>&ProductOrderIDList=<?=$eachOrder['ProductOrderIDList']?>"><span class="ver81" style="color:#005B00"><b><?=$eachOrder['ordno']?></b></span></a></td>
	<td align="left">
		<div style="height:13px; overflow-y:hidden;">
		<? if ($eachOrder['escrowyn']=="y"){ ?><img src="../img/btn_escrow.gif"/><? } ?>
		<? if ($eachOrder['cashreceipt']!=""){ ?><img src="../img/icon_cash_receipt.gif"/><? } ?>
		<span class="small1" style="color:#005700"><?=$goodsnm?></span>
		</div>
	</td>
	<td><span><span class="small1" style="color:#444444"><b><?=$eachOrder['nameOrder']?></b>(<?=$eachOrder['m_id']?>)</span></span></td>
	<td><span class="small1" style="color:#005700"><?=$eachOrder['nameReceiver']?></span></td>
	<td class="small4" style="color:#005700"><?=$checkout_message_schema['payMeansClassType'][$eachOrder['settlekind']]?></td>
	<td class="ver81" style="color:#005700"><b><?=number_format($eachOrder['prn_settleprice'])?></b></td>
	<!--<td class="small4" width="70" style="color:#005700"><?=getCheckoutOrderStatus($eachOrder)?></td>-->
	<td class="small4" width="70" style="color:#005700"><?=$eachOrder['step']?>..<?=$eachOrder['step2']?>..<?=$eachOrder['ProductOrderStatus']?></td>
</tr>
<!--
<?php if($eachOrder['ProductOrderStatus']=='PAYED' && $eachOrder['PlaceOrderStatus']=='OK' && $eachOrder['ClaimType']=='') { ?>
<tr id="tr_<?=$eachOrder['ordno']?>" height="25">
	<td colspan="4">&nbsp;</td>
	<td colspan="10">
		����� : <input type="text" name="DispatchDate[<?=$checkOutIndex?>]" value="" onclick="calendar(event)" readonly style="width:80px">

		��۹�� : <select name="DeliveryMethodCode[<?=$checkOutIndex?>]" style="width:110px;font-size:7pt;" class="el-DeliveryMethodCode">
		<option value="">(����)</option>
		<? foreach($checkout_message_schema['deliveryMethodType'] as $code => $name) { ?>
		<? if (strpos($code,'RETURN_') === 0) continue;?>
		<option value="<?=$code?>"><?=$name?></option>
		<? } ?>
		</select>

		�ù�� : <select name="DeliveryCompanyCode[<?=$checkOutIndex?>]" style="width:110px;font-size:7pt;">
		<option value="">(����)</option>
		<? foreach($checkout_message_schema['deliveryCompanyType'] as $code => $name) { ?>
		<option value="<?=$code?>"><?=$name?></option>
		<? } ?>
		</select>

		�����ȣ : <input type="text" name="TrackingNumber[<?=$checkOutIndex?>]" value=""   style="width:150px">
	</td>
</tr>
<?php } ?>
-->
<tr><td colspan="20" height="1px" bgcolor="#E4E4E4"></td></tr>

<?php
		else:
?>

<tr height="25" bgcolor="<?=$bgcolor?>" bg="<?=$bgcolor?>" align="center">
<!--
	<td class="noline"><input type="checkbox" name="chk[]" value="<?=$eachOrder['ordno']?>" class="chk_ordno_<?=$orderGroupKey?> chk_ordno" onclick="iciSelect(this)" <?=$disabled?>/></td>
-->
	<td><span class="ver8" style="color:#616161"><?=$eachOrder['_rno']?></span></td>
	<td><span class="ver81" style="color:#616161"><?=substr($eachOrder['orddt'],0,-3)?></span></td>
	<td align="left">
	<? if ($eachOrder['inflow'] == "sugi"){ ?>
	<a href="view.php?ordno=<?=$eachOrder['ordno']?>"><span class="ver81" style="color:#ED6C0A"><b><?=$eachOrder['ordno']?></b><span class="small1">(����)</span></span></a>
	<? } else { ?>
	<a href="view.php?ordno=<?=$eachOrder['ordno']?>"><span class="ver81" style="color:#0074BA"><b><?=$eachOrder['ordno']?></b></span></a>
	<? } ?>
	<a href="javascript:popup('popup.order.php?ordno=<?=$eachOrder['ordno']?>',800,600)"><img src="../img/btn_newwindow.gif" border=0 align="absmiddle"/></a>
	</td>
	<td align="left">
	<div style="height:13px; overflow-y:hidden;">
	<? if ($eachOrder['oldordno']!=""){ ?><a href="javascript:popup('popup.order.php?ordno=<?=$eachOrder['ordno']?>',800,600)"><img src="../img/icon_twice_order.gif"/></a><? } ?>
	<? if ($eachOrder['escrowyn']=="y"){ ?><a href="javascript:popup('popup.order.php?ordno=<?=$eachOrder['ordno']?>',800,600)"><img src="../img/btn_escrow.gif"/></a><? } ?>
	<? if ($eachOrder['eggyn']=="y"){ ?><a href="javascript:popup('popup.order.php?ordno=<?=$eachOrder['ordno']?>',800,600)"><img src="../img/icon_guar_order.gif"/></a><? } ?>
	<? if ($eachOrder['inflow']!=""&&$eachOrder['inflow']!="sugi"){ ?><a href="javascript:popup('popup.order.php?ordno=<?=$eachOrder['ordno']?>',800,600)"><img src="../img/inflow_<?=$eachOrder['inflow']?>.gif" align="absmiddle"/></a><? } ?>	<? if ($eachOrder['pCheeseOrdNo']!=""){ ?><a href="javascript:popup('popup.order.php?ordno=<?=$eachOrder['ordno']?>',800,600)"><img src="../img/icon_plus_cheese.gif" align="absmiddle"/></a><? } ?>
	<? if ($eachOrder['cashreceipt']!=""){ ?><img src="../img/icon_cash_receipt.gif"/><? } ?>
	<? if ($eachOrder['cbyn']=="Y"){ ?><a href="javascript:popup('popup.order.php?ordno=<?=$eachOrder['ordno']?>',800,600)"><img src="../img/icon_okcashbag.gif" align="absmiddle"/></a><? } ?>
	<span class="small1" style="color:#444444"><?=$goodsnm?></span>
	</div>

	</td>
	<td><? if ($eachOrder['m_id']) { ?><span id="navig" name="navig" m_id="<?=$eachOrder['m_id']?>" m_no="<?=$eachOrder['m_no']?>"><? } ?><span class="small1" style="color:#0074BA">
	<b><?=$eachOrder['nameOrder']?></b><br/>
	<? if ($eachOrder['m_id']) { ?>(<?=$eachOrder['m_id']?> / <?=$r_grp[ $eachOrder['level'] ]?>)<? } else { ?>(��ȸ��)<? } ?>
	</span><? if ($eachOrder['m_id']) { ?></span><? } ?>
	</td>
	<td><span class="small1" style="color:#444444;"><?=$eachOrder['nameReceiver']?></span></td>
	<td class="small4"><?=settleIcon($eachOrder['settleInflow']);?> <?=$r_settlekind[$eachOrder['settlekind']]?></td>
	<td class="ver81"><b><?=number_format($eachOrder['prn_settleprice'])?></b></td>
	<td class="small4" width="60">
		<? if($eachOrder['deliverycode'] || $eachOrder['count_dv_item']): ?>
			<a href="javascript:popup('popup.delivery.php?ordno=<?=$eachOrder['ordno']?>',800,500)" style="color:#0074BA"><?=$eachOrder['stepMsg']?></a>
		<? else: ?>
			<?=$eachOrder['step']?>..<?=$eachOrder['step2']?>..<?=$eachOrder['stepMsg']?>
		<? endif; ?>
	</td>
</tr>
<tr><td colspan="20" height="1px" bgcolor="#E4E4E4"></td></tr>

<?php
		endif;
	endforeach;
	$totalPrnSettlePrice+=$groupPrnSettlePrice;
?>
<tr>
<!--
	<td><a href="javascript:chkBoxGroup('<?=$orderGroupKey?>')"><img src="../img/btn_allchoice.gif" border="0"/></a></td>
-->
	<td height="30" colspan="9" align="right" style="padding-right:8px">�հ�: <span class="ver9"><b><?=number_format($groupPrnSettlePrice)?></span>��</b></td>
	<td colspan="3"></td>
</tr>
<tr><td colspan="13" height="15"></td></tr>
<?php
endforeach;
?>
<tr bgcolor="#f7f7f7" height="30">
	<td colspan="10" align="right" style="padding-right:8px">��ü�հ� : <span class="ver9"><b><?=number_format($totalPrnSettlePrice)?>��</b></span></td>
	<td colspan="3"></td>
</tr>
<tr><td height="4"></td></tr>
<tr><td colspan="12" class="rndline"></td></tr>
</table>

<?php if($search['mode']!='group'): ?>
	<div align="center" class="pageNavi ver8" style="font-weight:bold">
		<? if($pageNavi['prev']): ?>
			<a href="?<?=getvalue_chg('page',$pageNavi['prev'])?>">�� </a>
		<? endif; ?>
		<? foreach($pageNavi['page'] as $v): ?>
			<? if($v==$pageNavi['nowpage']): ?>
				<a href="?<?=getvalue_chg('page',$v)?>"><?=$v?></a>
			<? else: ?>
				<a href="?<?=getvalue_chg('page',$v)?>">[<?=$v?>]</a>
			<? endif; ?>
		<? endforeach; ?>
		<? if($pageNavi['next']): ?>
			<a href="?<?=getvalue_chg('page',$pageNavi['next'])?>">��</a>
		<? endif; ?>
	</div>
<?php endif; ?>
<!--
<div class="button">
<a href="javascript:processOrder()"><img src="../img/btn_modify.gif"/></a>
<a href="javascript:history.back()"><img src="../img/btn_cancel.gif"/></a>
</div>
-->
</form>

<form name="frmDnXls" method="post">
<input type="hidden" name="mode"/>
<input type="hidden" name="search" value="<?php echo htmlspecialchars(serialize($search));?>"/>
<input type="hidden" name="xls_itemcondition"/>
</form>

<!-- �ֹ����� ����Ʈ&�ٿ�ε� : Start -->
<table width="100%" border="0" cellpadding="10" cellspacing="0" style="border:1px #dddddd solid;">
<tr>
	<td width="50%" align="center" bgcolor="#f6f6f6" style="font:16pt tahoma;"><img src="../img/icon_down.gif" border="0" align="absmiddle"/><b>download</b></td>
	<td width="50%" align="center" bgcolor="#f6f6f6" style="font:16pt tahoma;border-left:1px #dddddd solid;"><img src="../img/icon_down.gif" border="0" align="absmiddle"/><b>print</b></td>
</tr>
<tr>
	<td align="center">

	<table class="tb">
	<tr>
		<td><span class="small1">�����ٿ�ε� ����</span></td>
		<td colspan="3" class="noline">
			<input type="radio" name="itemcondition" id="itemcondition_all" value="" <?=frmChecked('',$search['itemcondition'])?> /><label for="itemcondition_all">��ü</label>
			<input type="radio" name="itemcondition" id="itemcondition_N" value="N" <?=frmChecked('N',$search['itemcondition'])?> /><label for="itemcondition_N">���� �ֹ���ǰ�� ��������</label>
			<input type="radio" name="itemcondition" id="itemcondition_A" value="A" <?=frmChecked('A',$search['itemcondition'])?> /><label for="itemcondition_A">������ �ֹ���ǰ�� ��������</label>
		</td>
	</tr>
	</table>

	<table border="0" cellpadding="4" cellpadding="0" border="0">
	<tr align="center">
	<td><a href="javascript:dnXls('order')"><img src="../img/btn_order_data_order.gif" border="0"/></a></td>
	<td><a href="javascript:dnXls('goods')"><img src="../img/btn_order_data_goods.gif" border="0"/></a></td>
	</tr>
	<tr align="center">
	<td><a href="javascript:popupLayer('../data/popup.orderxls.php',550,700)"><img src="../img/btn_order_data_order_ot.gif" border="0"/></a></td>
	<td><a href="javascript:popupLayer('../data/popup.orderxls.php?mode=orderGoodsXls',550,700)"><img src="../img/btn_order_data_goods_ot.gif" border="0"/></a></td>
	</tr>
	</table>
	</td>
	<td align="center" style="border-left:1px #dddddd solid;">
	<form method="get" name="frmPrint">
	<input type="hidden" name="ordnos"/>

	<table border="0" cellpadding="4" cellpadding="0" border="0">
	<tr align="center">
	<td><select NAME="type">
	<option value="report">�ֹ�������</option>
	<option value="report_customer">�ֹ�������(����)</option>
	<option value="reception">���̿�����</option>
	<option value="tax">���ݰ�꼭</option>
	<option value="particular">�ŷ�����</option>
	</select></td>
	</tr>
	<tr>
	<td align="center"><strong class=noline><label for="r1"><input class="no_line" type="radio" name="list_type" value="list" id="r1" onclick="openLayer('psrch','none')" checked>��ϼ���</input></label>&nbsp;&nbsp;&nbsp;<label for="r2"><input class="no_line" type="radio" name="list_type" value="term" id="r2" onclick="openLayer('psrch','block')">�Ⱓ����</input></label></strong></td>
	</tr>
	<tr>
	<td align="cemter"><div style="float:left; display:none;" id="psrch">
	<input type="text" name="regdt[]" onclick="calendar(event)" size="12" class="line"/> -
	<input type="text" name="regdt[]" onclick="calendar(event)" size="12" class="line"/>
	<select name="settlekind">
	<option value=""> - ������� - </option>
	<? foreach ( $r_settlekind as $k => $v ) echo "<option value=\"{$k}\">{$v}</option>"; ?>
	</select>
	<select name="step">
	<option value=""> - �ܰ輱�� - </option>
	<? foreach ( $r_step as $k => $v ) echo "<option value=\"step_{$k}\">{$v}</option>"; ?>
	<option value="step2_1">�ֹ����</option>
	<option value="step2_2">ȯ�Ұ���</option>
	<option value="step2_3">��ǰ����</option>
	<option value="step2_50">�����õ�</option>
	<option value="step2_54">��������</option>
	</select>
	</div></td>
	</tr>
	<tr>
	<td align="center"><a href="javascript:order_print('frmPrint', 'frmList');" style="padding-top:20px"><img src="../img/btn_print.gif" border="0" align="absmiddle"/></a></td>
	</tr>
	</table>
	</form>
	</td>
</tr>
</table>
<!-- �ֹ����� ����Ʈ : End -->

<div id="MSG01">
<table cellpadding="1" cellspacing="0" border="0" class="small_ex">
<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>�ֹ��� �Ǵ� �ֹ�ó���帧 ������� �ֹ������� �����Ͻ� �� �ֽ��ϴ�.</td></tr>
<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>�ֹ����¸� �����Ͻ÷��� �ֹ��� ���� - ó���ܰ輱�� �� ������ư�� ��������.</td></tr>
<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>�ֹ����º����� ���� �� �ֹ�ó���ܰ� (�ֹ�����, �Ա�Ȯ��, ����غ�, �����, ��ۿϷ�) �� ������  ó���Ͻ� �� �ֽ��ϴ�.</td></tr>

<tr><td height="8"></td></tr>
<tr><td><span class="def1"><b>- ī������ֹ��� �Ʒ��� ���� ��찡 �߻��� �� �ֽ��ϴ�. (�ʵ��ϼ���!) -</span></td></tr>

<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>�ش� PG�� �����ڸ�忡�� ������ �Ǿ�����, �ֹ�����Ʈ���� �ֹ����°� '�Ա�Ȯ��'�� �ƴ� '�����õ�'�� �Ǿ� �ִ� ��찡 �߻��� �� �ֽ��ϴ�.</td></tr>
<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>�̴� �߰��� ��Ż��� ������ ���ϰ��� ����� ���� ���� �ֹ����°� ������ ���� ���� ���Դϴ�.</td></tr>
<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>��, �̿Ͱ��� ������ �Ǿ����� �ֹ����°� '�����õ�'�� ��� �ش��ֹ����� �ֹ��󼼳��� ���������� "�����õ�, ���� ����" ó���� �Ͻø� �ֹ�ó�����°� "�Ա�Ȯ��"���� �����˴ϴ�.</td></tr>
<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>�׷��� �������� ���ϰ��� �޾� �ֹ�ó�����°� ����� ���̱⿡ �̿� ���ؼ��� ��Ȯ�� �����α׸� �ֹ��󼼳������������� Ȯ���� �� �� �����ϴ�.</td></tr>
<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>���� ���� ī������� �ֹ��� 1�� �����ߴµ� ��Ȥ PG�� �ʿ����� 2���� ����(�ߺ�����)�Ǵ� ��찡 �ֽ��ϴ�.</td></tr>
<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>�� ���� �ش� PG���� �����ڸ��� ���� �ߺ����ε� 2���߿� 1���� ������� ���ֽø� �˴ϴ�.</td></tr>
<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>�ߺ����ΰ��� üũ�ؼ� �ٷ� �������ó������ ������ �̼����� �߻��Ǿ� ���̰� �ǰ�, �ش� PG��κ��� �ŷ�������û ���� �������� ���� �� �ֽ��ϴ�.</td></tr>
<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>�������ΰ��� �ֹ����¿� �ߺ����ΰ� ó���� �����ϰ� üũ�ؾ� �ϸ� �̿� ���� å���� ���θ� ��ڿ��� �ֽ��ϴ�.</td></tr>
<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>�׻� ī��������� �̰� �ֹ�����Ʈ�� PG�翡�� �����ϴ� ������������ �������ΰǰ� ���ϸ鼭 ���Ǳ�� üũ�Ͽ� ó���Ͻñ� �ٶ��ϴ�.</td></tr>
</table>
</div>
<script>cssRound('MSG01')</script>

<?
include "_deliveryForm.php"; //�����ϰ��Է���
?>

<script>window.onload = function(){ UNM.inner();};</script>
<? @include dirname(__FILE__) . "/../interpark/_order_list.php"; // ������ũ_��Ŭ��� ?>

<? include "../_footer.php"; ?>