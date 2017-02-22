<?php
$location = "신규통계";
include "../_header.php";
@include "../../conf/config.pay.php";
include "../../lib/page.class.php";
@include "../../conf/phone.php";


if (get_magic_quotes_gpc()) {
	stripslashes_all($_POST);
	stripslashes_all($_GET);
}

// $_GET으로 받는 모든 값 정의
$search=array(
	'first'=>(string)$_GET['first'], // 처음 목록화면을 열었는지에 대한 값
	'regdt_start'=>(string)$_GET['regdt'][0], // 처리일자 시작
	'regdt_end'=>(string)$_GET['regdt'][1], // 처리일자 끝
	'dtkind'=>(string)($_GET['dtkind'] ? $_GET['dtkind'] : 'orddt'), // 처리일자 종류
	'mode'=>(string)$_GET['mode'], // 목록 형식 ( 주문일로 보기 , 주문처리흐름으로 보기 )
	'sword'=>trim((string)$_GET['sword']), // 검색어
	'skey'=>($_GET['skey'] ? (string)$_GET['skey'] : 'all'), // 검색필드
	'sgword'=>trim((string)$_GET['sgword']), // 상품 검색어
	'sgkey'=>(string)$_GET['sgkey'], // 상품 검색필드
	'step'=>(array)$_GET['step'], // 주문상태
	'step2'=>(array)$_GET['step2'], // 주문상태
	'settlekind'=>(string)$_GET['settlekind'], // 결제방법
	'escrowyn'=>(string)$_GET['escrowyn'], // 에스크로
	'eggyn'=>(string)$_GET['eggyn'], // 전자보증보험
	'chk_inflow'=>(array)$_GET['chk_inflow'], // 제휴처주문
	'couponyn'=>(string)$_GET['couponyn'], // 쿠폰사용
	'cashreceipt'=>(string)$_GET['cashreceipt'], // 현금영수증
	'cbyn'=>(string)$_GET['cbyn'], // OKCashbag적립
	'aboutcoupon'=>(string)$_GET['aboutcoupon'], // 어바웃쿠폰
	'mobilepay'=>(string)$_GET['mobilepay'], // 모바일샵
	'todaygoods'=>(string)$_GET['todaygoods'], // 투데이샵
	'regdt_time_start'=>$_GET['regdt_time'] ? (int)$_GET['regdt_time'][0] : -1, // 처리일자 시간대
	'regdt_time_end'=>$_GET['regdt_time'] ? (int)$_GET['regdt_time'][1] : -1, // 처리일자 시간대
	'sugi'=>(string)$_GET['sugi'],
	'itemcondition'=>$_GET['itemcondition'],
	's_prn_settleprice'=>(string)$_GET['prn_settleprice'][0], // 결제금액 시작
	'e_prn_settleprice'=>(string)$_GET['prn_settleprice'][1], // 결제금액 끝
	'payco'=>(string)$_GET['payco'], // 페이코
);
$page = (int)$_GET['page'] ? (int)$_GET['page'] : 1;

// first 인자값에 대한 처리일자 기본값 정의
if($search['first']) {
	if(!$cfg['orderPeriod']) $cfg['orderPeriod']=0;
	$search['regdt_start'] = date('Ymd',strtotime('-'.$cfg['orderPeriod'].' day'));
	$search['regdt_end'] = date('Ymd');
}
$search['first']=0;

// 변수검증
if(!in_array($search['dtkind'],array('orddt','cdt','ddt','confirmdt'))) { exit; }
if(!in_array($search['skey'],array('all','ordno','nameOrder','nameReceiver','bankSender','m_id','mobileOrder'))) { exit; }
if(!in_array($search['sgkey'],array('','goodsnm','brandnm','maker'))) { exit; }
foreach($search['step'] as $k=>$v) { $search['step'][$k]=(int)$v; }
foreach($search['step2'] as $k=>$v) { $search['step2'][$k]=(int)$v; }

// 쿼리문을 위한 검색조건 만들기
$isOrderItemSearch=false;
$arWhere = array();
// 접수유형
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
				case 60 : $subWhere[] = "(oi.dyn='e' and oi.cyn='e')"; $isOrderItemSearch=true; break; //교환완료
				case 61 : $subWhere[] = "o.oldordno != ''";break; //재주문
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

//주문금액 검색
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

// gd_order_item 에서 검색조건이 발생하는 경우 상품갯수와 상품송장체크는 별도로 처리
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
// 쿼리 실행
/* ++상품번호 별 그룹핑 하기 위한 array */
$goodsList=array();
/* --상품번호 별 그룹핑 하기 위한 array */
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

	// 그룹별로 주문서 할당
	foreach($result as $v) {
		$orderGroupKey = $v['step2']*10+($v['step'] === '1' || ($v['step'] === '2' && $v['step2'] > 40) ? 1 : $v['step']);
		$orderGroupNameMap[$orderGroupKey] = getStepMsg($v['step'],$v['step2']);
		$orderList[$orderGroupKey][] = $v;
	}
	ksort($orderList);

	// 정렬
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
	
	// 그룹별로 주문서 할당
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
	// 정렬
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

### 그룹명 가져오기
$r_grp = array();
$garr = member_grp();
foreach( $garr as $v ) $r_grp[$v['level']] = $v['grpnm'];
?>

<script type="text/javascript">
/**
* 라인색상 활성화
*/
function iciSelect(obj) {
	var row = obj.parentNode.parentNode;
	row.style.background = (obj.checked) ? "#F9FFA1" : row.getAttribute('bg');
	if($('tr_'+obj.value)) {
		$('tr_'+obj.value).style.background=row.style.background;
	}
}

/**
* 전체선택
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
* 그룹선택
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
* 주문상태변경수정
*/
function processOrder() {
	f = $('frmList');
	var selCase = f.select('select[name=case]')[0];
	var isGodoChk=false;

	if(!selCase.value) {
		alert('주문상태값을 선택해주세요');
		return;
	}

	f.select("input[type=checkbox]").each(function(item){
		var re = new RegExp('^chk');
		if(re.test(item.name) && item.checked) {
			isGodoChk=true;
		}
	});

	// 네이버체크아웃
	if (typeof(processCheckoutOrder) == 'function') {
		if (processCheckoutOrder(f, selCase, isGodoChk) === false) {
			return;
		}
	}

	if(isGodoChk) {
		f.submit();
	}
	else {
		alert('주문건을 선택해주세요');
	}
}

/**
* 엑셀파일 다운로드
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

<div class="title title_top" style="position:relative;padding-bottom:15px">상품별 주문 현황<span>상품별 주문 현황을 확인합니다</span>
<a href="javascript:manual('<?=$guideUrl?>board/view.php?id=order&no=2')"><img src="../img/btn_q.gif" border="0" hspace="2" align="absmiddle"/></a>

</div>

<form>
<input type="hidden" name="mode" value="<?=$search['mode']?>"/>

<table class="tb">
<col class="cellC"><col class="cellL" style="width:300px">
<col class="cellC"><col class="cellL">
<tr>
	<td><span class="small1">주문검색 (통합)</span></td>
	<td>
	<select name="skey">
	<option value="all"> = 통합검색 = </option>
	<option value="ordno" <?=frmSelected($search['skey'],'ordno');?>> 주문번호</option>
	<option value="nameOrder" <?=frmSelected($search['skey'],'nameOrder');?>> 주문자명</option>
	<option value="nameReceiver" <?=frmSelected($search['skey'],'nameReceiver');?>> 수령자명</option>
	<option value="bankSender" <?=frmSelected($search['skey'],'bankSender');?>> 입금자명</option>
	<option value="m_id" <?=frmSelected($search['skey'],'m_id');?>> 아이디</option>
	<option value="mobileOrder" <?=frmSelected($search['skey'],'mobileOrder');?>> 주문자 핸드폰번호</option>
	</select>
	<input type="text" name="sword" value="<?=htmlspecialchars($search['sword'])?>" class="line"/>
	</td>
	<td><span class="small1">상품검색 (선택)</span></td>
	<td>
	<select name="sgkey">
	<option value="goodsnm" <?=frmSelected($search['sgkey'],'goodsnm');?>> 상품명</option>
	<option value="brandnm" <?=frmSelected($search['sgkey'],'brandnm');?>> 브랜드</option>
	<option value="maker" <?=frmSelected($search['sgkey'],'maker');?>> 제조사</option>
	</select>
	<input type=text name="sgword" value="<?=htmlspecialchars($search['sgword'])?>" class="line"/>
	</td>
</tr>
<tr>
	<td><span class="small1">접수유형</span></td>
	<td class="noline"><span class="small1" style="color:#5C5C5C;">
	<input type="radio" name="sugi" id="sugi_all" value="" <?=frmChecked('',$search['sugi'])?> /><label for="sugi_all">전체</label>
	<input type="radio" name="sugi" id="sugi_N" value="N" <?=frmChecked('N',$search['sugi'])?> /><label for="sugi_N">온라인접수</label>
	<input type="radio" name="sugi" id="sugi_Y" value="Y" <?=frmChecked('Y',$search['sugi'])?> /><label for="sugi_Y">수기접수</label>
	</span></td>
	<td><span class="small1">결제금액</span></td>
	<td>
	<input type="text" name="prn_settleprice[]" value="<?=$search['s_prn_settleprice']?>" size="10" onkeydown="onlynumber();" class="rline" />원 ~
	<input type="text" name="prn_settleprice[]" value="<?=$search['e_prn_settleprice']?>" size="10" onkeydown="onlynumber();" class="rline" />원
	</td>
</tr>

<tr>
	<td><span class="small1">주문상태</span></td>
	<td colspan="3" class="noline">
	<? foreach ($r_step as $k=>$v){ ?>
	<div style="float:left; padding-right:10px; color:#5C5C5C;"><input type="checkbox" name="step[]" value="<?=$k?>" <?=(in_array($k,$search['step'])?'checked':'')?>><span class="small1"><?=$v?></span></input></div>
	<? } ?>
	<div style="float:left; padding-right:10px; color:#5C5C5C;"><input type="checkbox" name="step2[]" value="1" <?=(in_array(1,$search['step2'])?'checked':'')?>><span class="small1">주문취소</span></input></div>
	<div style="clear:both;"></div>
	<div style="float:left; padding-right:10px; color:#5C5C5C;"><input type="checkbox" name="step2[]" value="2" <?=(in_array(2,$search['step2'])?'checked':'')?>><span class="small1">환불관련</span></input></div>
	<div style="float:left; padding-right:10px; color:#5C5C5C;"><input type="checkbox" name="step2[]" value="3" <?=(in_array(3,$search['step2'])?'checked':'')?>><span class="small1">반품관련</span></input></div>
	<div style="float:left; padding-right:10px; color:#5C5C5C;"><input type="checkbox" name="step2[]" value="60" <?=(in_array(60,$search['step2'])?'checked':'')?>><span class="small1">교환완료</span></input></div>
	<div style="float:left; padding-right:10px; color:#5C5C5C;"><input type="checkbox" name="step2[]" value="61" <?=(in_array(61,$search['step2'])?'checked':'')?>><span class="small1">재주문</span></input></div>
	<div style="float:left; padding-right:10px; color:#5C5C5C;"><input type="checkbox" name="step2[]" value="50" <?=(in_array(50,$search['step2'])?'checked':'')?>><span class="small1">결제시도</span></input></div>
	<div style="float:left; padding-right:10px; color:#5C5C5C;"><input type="checkbox" name="step2[]" value="54" <?=(in_array(54,$search['step2'])?'checked':'')?>><span class="small1">결제실패</span></input></div>
	<div style="float:left; padding-right:10px; color:#5C5C5C;"><input type="checkbox" name="step2[]" value="51" <?=(in_array(51,$search['step2'])?'checked':'')?>><span class="small1">PG확인요망</span></input></div>
	</td>
</tr>
<tr>
	<td><span class="small1">처리일자</span></td>
	<td colspan="3">
	<span class="noline small1" style="color:5C5C5C; margin-right:20px;">
	<input type="radio" name="dtkind" value="orddt" <?=frmChecked($search['dtkind'],'orddt')?>>주문일</input>
	<input type="radio" name="dtkind" value="cdt" <?=frmChecked($search['dtkind'],'cdt')?>>결제확인일</input>
	<input type="radio" name="dtkind" value="ddt" <?=frmChecked($search['dtkind'],'ddt')?>>배송일</input>
	<input type="radio" name="dtkind" value="confirmdt" <?=frmChecked($search['dtkind'],'confirmdt')?>>배송완료일</input>
	</span>

	<input type="text" name="regdt[]" value="<?=$search['regdt_start']?>" onclick="calendar(event)" size="12" class="line"/>
	<select name="regdt_time[]">
	<option value="-1">---</option>
	<? for ($i=0;$i<24;$i++) {?>
	<option value="<?=$i?>" <?=($search['regdt_time_start'] === $i ? 'selected' : '')?>><?=sprintf('%02d',$i)?>시</option>
	<? } ?>
	</select>
	-
	<input type="text" name="regdt[]" value="<?=$search['regdt_end']?>" onclick="calendar(event)" size="12" class="line"/>
	<select name="regdt_time[]">
	<option value="-1">---</option>
	<? for ($i=0;$i<24;$i++) {?>
	<option value="<?=$i?>" <?=($search['regdt_time_end'] === $i ? 'selected' : '')?>><?=sprintf('%02d',$i)?>시</option>
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
	<td><span class="small1">결제방법</span></td>
	<td colspan="3" class="noline"><span class="small1" style="color:#5C5C5C;">
	<input type="radio" name="settlekind" value="" <?=frmChecked('',$search['settlekind'])?>>전체</input>
	<input type="radio" name="settlekind" value="a" <?=frmChecked('a',$search['settlekind'])?>>무통장</input>
	<input type="radio" name="settlekind" value="c" <?=frmChecked('c',$search['settlekind'])?>>신용카드</input>
	<input type="radio" name="settlekind" value="o" <?=frmChecked('o',$search['settlekind'])?>>계좌이체</input>
	<input type="radio" name="settlekind" value="v" <?=frmChecked('v',$search['settlekind'])?>>가상계좌</input>
	<input type="radio" name="settlekind" value="h" <?=frmChecked('h',$search['settlekind'])?>>핸드폰</input>
	<? if ($cfg['settlePg'] == "inipay") { ?>
	<input type="radio" name="settlekind" value="y" <?=frmChecked('y',$search['settlekind'])?>>옐로페이</input>
	<? } ?>
	<input type="radio" name="settlekind" value="d" <?=frmChecked('d',$search['settlekind'])?>>전액할인</input><br>
	<input type="checkbox" name="payco" value="1" <?=frmChecked('1',$search['payco'])?>><img src="../img/icon_payco.gif"/>페이코</input>
	<input type="checkbox" name="couponyn" value="1" <?=frmChecked('1',$search['couponyn'])?>>쿠폰사용</input>
	<input type="checkbox" name="cashreceipt" value="1" <?=frmChecked('1',$search['cashreceipt'])?>>현금영수증 <img src="../img/icon_cash_receipt.gif"/></input>
	<input type="radio" name="settlekind" value="p" <?=frmChecked('p',$search['settlekind'])?>>포인트</input>
	<input type="checkbox" name="cbyn" value="Y" <?=frmChecked('p',$search['cbyn'])?>><img src="../img/icon_okcashbag.gif" align="absmiddle"/>OKCashBag적립</input>
	<input type="checkbox" name="aboutcoupon" value="1" <?=frmChecked('1',$search['aboutcoupon'])?>>어바웃쿠폰</input>
	</span>
	</td>
</tr>
<tr>
	<td><span class="small1">모바일샵</span></td>
	<td colspan="3" class="noline"><span class="small1" style="color:#5C5C5C;">
	<input type="radio" name="mobilepay" value="" <?=frmChecked('',$search['mobilepay'])?>>전체</input>
	<input type="radio" name="mobilepay" value="n" <?=frmChecked('n',$search['mobilepay'])?>>일반결제</input>
	<input type="radio" name="mobilepay" value="y" <?=frmChecked('y',$search['mobilepay'])?>>모바일샵결제</input>
	</td>
</tr>
<tr>
	<td><font class="small1">투데이샵</td>
	<td colspan="3" class="noline"><font class="small1" color="5C5C5C">
	<input type="radio" name="todaygoods" value="" <?=frmChecked('',$search['todaygoods'])?>>전체
	<input type="radio" name="todaygoods" value="n" <?=frmChecked('n',$search['todaygoods'])?>>일반상품
	<input type="radio" name="todaygoods" value="y" <?=frmChecked('y',$search['todaygoods'])?>>투데이샵상품
	</td>
</tr>
<tr>
	<td><span class="small1">에스크로</span></td>
	<td class="noline"><span class="small1" style="color:#5C5C5C;">
	<input type="radio" name="escrowyn" value="" <?=frmChecked('',$search['escrowyn'])?>>전체</input>
	<input type="radio" name="escrowyn" value="n" <?=frmChecked('n',$search['escrowyn'])?>>일반결제</input>
	<input type="radio" name="escrowyn" value="y" <?=frmChecked('y',$search['escrowyn'])?>>에스크로 <img src="../img/btn_escrow.gif" align="absmiddle"/></input>
	</td>
	<td><span class="small1">전자보증보험</span> <a href="../basic/egg.intro.php"><img src="../img/btn_question.gif"/></a></td>
	<td class="noline"><span class="small1" style="color:#5C5C5C;">
	<input type="radio" name="eggyn" value="" <?=frmChecked('',$search['eggyn'])?>>전체</input>
	<input type="radio" name="eggyn" value="n" <?=frmChecked('n',$search['eggyn'])?>>미발급</input>
	<input type="radio" name="eggyn" value="f" <?=frmChecked('f',$search['eggyn'])?>>발급실패</input>
	<input type="radio" name="eggyn" value="y" <?=frmChecked('y',$search['eggyn'])?>>발급완료 <img src="../img/icon_guar_order.gif"/></input>
	</td>
</tr>
<tr>
	<td><span class="small1">제휴처주문</span> <a href="../naver/naver.php"><img src="../img/btn_question.gif"/></a></td>
	<td colspan="3" class="noline"><span class="small1" style="color:#5C5C5C;">
	<input type="checkbox" name="chk_inflow[]" value="naver" <?=(in_array('naver',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_naver.gif" align="absmiddle"/> 네이버지식쇼핑</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="naver_pchs_040901" <?=(in_array('naver_pchs_040901',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_naver_pchs_040901.gif" align="absmiddle"/> 네이버지식쇼핑추천광고</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="yahoo_fss" <?=(in_array('yahoo_fss',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_yahoo_fss.gif" align="absmiddle"/> 야후패션소호</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="interpark" <?=(in_array('interpark',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_interpark.gif" align="absmiddle"/> 인터파크샵플러스</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="openstyle" <?=(in_array('openstyle',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_interpark.gif" align="absmiddle"/> 인터파크오픈스타일</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="openstyleOutlink" <?=(in_array('openstyleOutlink',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_interpark.gif" align="absmiddle"/> 인터파크오픈스타일아웃링크</input><br>
	<input type="checkbox" name="chk_inflow[]" value="naver_price" <?=(in_array('naver_price',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_naver_price.gif" align="absmiddle"/> 네이버가격비교</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="danawa" <?=(in_array('danawa',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_danawa.gif" align="absmiddle"/> 다나와</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="mm" <?=(in_array('mm',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_mm.gif" align="absmiddle"/> 마이마진</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="bb" <?=(in_array('bb',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_bb.gif" align="absmiddle"/> 베스트바이어</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="omi" <?=(in_array('omi',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_omi.gif" align="absmiddle"/> 오미</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="enuri" <?=(in_array('enuri',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_enuri.gif" align="absmiddle"/> 에누리</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="yahoo" <?=(in_array('yahoo',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_yahoo.gif" align="absmiddle"/> 야후가격비교</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="yahooysp" <?=(in_array('yahooysp',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_yahooysp.gif" align="absmiddle"/> 야후전문몰</input><br />
	<input type="checkbox" name="chk_inflow[]" value="auctionos" <?=(in_array('auctionos',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_auctionos.gif" align="absmiddle"/> 옥션어바웃</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="daumCpc" <?=(in_array('daumCpc',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_daumCpc.gif" align="absmiddle"/> 다음쇼핑하우</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="cywordScrap" <?=(in_array('cywordScrap',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_cywordScrap.gif" align="absmiddle"/> 싸이월드스크랩</input>&nbsp;
	<input type="checkbox" name="chk_inflow[]" value="naverCheckout" <?=(in_array('naverCheckout',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_naverCheckout.gif" align="absmiddle"/> 네이버체크아웃</input>
	<input type="checkbox" name="chk_inflow[]" value="plus_cheese" <?=(in_array('plus_cheese',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_plus.gif" align="absmiddle"/> 플러스치즈</input>
	<input type="checkbox" name="chk_inflow[]" value="auctionIpay" <?=(in_array('auctionIpay',$search['chk_inflow'])?'checked':'')?>><img src="../img/inflow_auctionIpay.gif" align="absmiddle"/> 옥션iPay</input>
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
	<td>아래에서 선택한 주문건을
	<select name="case" required label="수정사항">
	<option value="">- 주문상태 변경처리 -</option>
	<option value="0">주문접수 처리</option>
	<option value="1">입금확인 처리</option>
	<option value="2">배송준비중 처리</option>
	<option value="3">배송중 처리</option>
	<option value="4">배송완료 처리</option>
	</select> 합니다. <span class="extext">(변경후 하단 수정버튼 꼭 클릭)</span>
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
	<th><a href="javascript:void(0)" onClick="chkBoxAll()" class=white>선택</a></th>
-->
	<th>번호</th>
	<th>주문일시</th>
	<th colspan="2">주문번호 (주문상품)</th>
	<th>주문자</th>
	<th>받는분</th>
	<th>결제</th>
	<th>금액</th>
	<th colspan="6">처리상태</th>
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
		if($eachOrder['count_item']>1) $goodsnm = $eachOrder['goodsnm'].' 외'.($eachOrder['count_item']-1).'건';
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
		-- 발주 확인 --
		<input type="checkbox" name="PlaceProductOrder_OrderID[<?=$checkOutIndex?>]" value="<?=$eachOrder['ordno']?>" class="chk_ordno {ProductOrderStatus:'<?=$eachOrder['ProductOrderStatus']?>',ClaimType:'<?=$eachOrder['ClaimType']?>',ClaimStatus:'<?=$eachOrder['ClaimStatus']?>',PlaceOrderStatus:'<?=$eachOrder['PlaceOrderStatus']?>'}" onclick="iciSelect(this)"/>

		<?php } elseif($eachOrder['ProductOrderStatus']=='PAYED' && $eachOrder['PlaceOrderStatus']=='OK' AND $eachOrder['ClaimStatus']=='') { ?>
		-- 발송 처리 --
		<input type="checkbox" name="ShipProductOrder_OrderID[<?=$checkOutIndex?>]" value="<?=$eachOrder['ordno']?>" class="chk_ordno {ProductOrderStatus:'<?=$eachOrder['ProductOrderStatus']?>',ClaimType:'<?=$eachOrder['ClaimType']?>',ClaimStatus:'<?=$eachOrder['ClaimStatus']?>',PlaceOrderStatus:'<?=$eachOrder['PlaceOrderStatus']?>'}" onclick="iciSelect(this)"/>

		<?php } else { ?>
		-- 그외 처리 할수 없음 --
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
		배송일 : <input type="text" name="DispatchDate[<?=$checkOutIndex?>]" value="" onclick="calendar(event)" readonly style="width:80px">

		배송방법 : <select name="DeliveryMethodCode[<?=$checkOutIndex?>]" style="width:110px;font-size:7pt;" class="el-DeliveryMethodCode">
		<option value="">(선택)</option>
		<? foreach($checkout_message_schema['deliveryMethodType'] as $code => $name) { ?>
		<? if (strpos($code,'RETURN_') === 0) continue;?>
		<option value="<?=$code?>"><?=$name?></option>
		<? } ?>
		</select>

		택배사 : <select name="DeliveryCompanyCode[<?=$checkOutIndex?>]" style="width:110px;font-size:7pt;">
		<option value="">(선택)</option>
		<? foreach($checkout_message_schema['deliveryCompanyType'] as $code => $name) { ?>
		<option value="<?=$code?>"><?=$name?></option>
		<? } ?>
		</select>

		송장번호 : <input type="text" name="TrackingNumber[<?=$checkOutIndex?>]" value=""   style="width:150px">
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
	<a href="view.php?ordno=<?=$eachOrder['ordno']?>"><span class="ver81" style="color:#ED6C0A"><b><?=$eachOrder['ordno']?></b><span class="small1">(수기)</span></span></a>
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
	<? if ($eachOrder['m_id']) { ?>(<?=$eachOrder['m_id']?> / <?=$r_grp[ $eachOrder['level'] ]?>)<? } else { ?>(비회원)<? } ?>
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
	<td height="30" colspan="9" align="right" style="padding-right:8px">합계: <span class="ver9"><b><?=number_format($groupPrnSettlePrice)?></span>원</b></td>
	<td colspan="3"></td>
</tr>
<tr><td colspan="13" height="15"></td></tr>
<?php
endforeach;
?>
<tr bgcolor="#f7f7f7" height="30">
	<td colspan="10" align="right" style="padding-right:8px">전체합계 : <span class="ver9"><b><?=number_format($totalPrnSettlePrice)?>원</b></span></td>
	<td colspan="3"></td>
</tr>
<tr><td height="4"></td></tr>
<tr><td colspan="12" class="rndline"></td></tr>
</table>

<?php if($search['mode']!='group'): ?>
	<div align="center" class="pageNavi ver8" style="font-weight:bold">
		<? if($pageNavi['prev']): ?>
			<a href="?<?=getvalue_chg('page',$pageNavi['prev'])?>">◀ </a>
		<? endif; ?>
		<? foreach($pageNavi['page'] as $v): ?>
			<? if($v==$pageNavi['nowpage']): ?>
				<a href="?<?=getvalue_chg('page',$v)?>"><?=$v?></a>
			<? else: ?>
				<a href="?<?=getvalue_chg('page',$v)?>">[<?=$v?>]</a>
			<? endif; ?>
		<? endforeach; ?>
		<? if($pageNavi['next']): ?>
			<a href="?<?=getvalue_chg('page',$pageNavi['next'])?>">▶</a>
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

<!-- 주문내역 프린트&다운로드 : Start -->
<table width="100%" border="0" cellpadding="10" cellspacing="0" style="border:1px #dddddd solid;">
<tr>
	<td width="50%" align="center" bgcolor="#f6f6f6" style="font:16pt tahoma;"><img src="../img/icon_down.gif" border="0" align="absmiddle"/><b>download</b></td>
	<td width="50%" align="center" bgcolor="#f6f6f6" style="font:16pt tahoma;border-left:1px #dddddd solid;"><img src="../img/icon_down.gif" border="0" align="absmiddle"/><b>print</b></td>
</tr>
<tr>
	<td align="center">

	<table class="tb">
	<tr>
		<td><span class="small1">엑셀다운로드 조건</span></td>
		<td colspan="3" class="noline">
			<input type="radio" name="itemcondition" id="itemcondition_all" value="" <?=frmChecked('',$search['itemcondition'])?> /><label for="itemcondition_all">전체</label>
			<input type="radio" name="itemcondition" id="itemcondition_N" value="N" <?=frmChecked('N',$search['itemcondition'])?> /><label for="itemcondition_N">정상 주문상품만 엑셀포함</label>
			<input type="radio" name="itemcondition" id="itemcondition_A" value="A" <?=frmChecked('A',$search['itemcondition'])?> /><label for="itemcondition_A">비정상 주문상품만 엑셀포함</label>
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
	<option value="report">주문내역서</option>
	<option value="report_customer">주문내역서(고객용)</option>
	<option value="reception">간이영수증</option>
	<option value="tax">세금계산서</option>
	<option value="particular">거래명세서</option>
	</select></td>
	</tr>
	<tr>
	<td align="center"><strong class=noline><label for="r1"><input class="no_line" type="radio" name="list_type" value="list" id="r1" onclick="openLayer('psrch','none')" checked>목록선택</input></label>&nbsp;&nbsp;&nbsp;<label for="r2"><input class="no_line" type="radio" name="list_type" value="term" id="r2" onclick="openLayer('psrch','block')">기간선택</input></label></strong></td>
	</tr>
	<tr>
	<td align="cemter"><div style="float:left; display:none;" id="psrch">
	<input type="text" name="regdt[]" onclick="calendar(event)" size="12" class="line"/> -
	<input type="text" name="regdt[]" onclick="calendar(event)" size="12" class="line"/>
	<select name="settlekind">
	<option value=""> - 결제방법 - </option>
	<? foreach ( $r_settlekind as $k => $v ) echo "<option value=\"{$k}\">{$v}</option>"; ?>
	</select>
	<select name="step">
	<option value=""> - 단계선택 - </option>
	<? foreach ( $r_step as $k => $v ) echo "<option value=\"step_{$k}\">{$v}</option>"; ?>
	<option value="step2_1">주문취소</option>
	<option value="step2_2">환불관련</option>
	<option value="step2_3">반품관련</option>
	<option value="step2_50">결제시도</option>
	<option value="step2_54">결제실패</option>
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
<!-- 주문내역 프린트 : End -->

<div id="MSG01">
<table cellpadding="1" cellspacing="0" border="0" class="small_ex">
<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>주문일 또는 주문처리흐름 방식으로 주문내역을 정렬하실 수 있습니다.</td></tr>
<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>주문상태를 변경하시려면 주문건 선택 - 처리단계선택 후 수정버튼을 누르세요.</td></tr>
<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>주문상태변경을 통해 각 주문처리단계 (주문접수, 입금확인, 배송준비, 배송중, 배송완료) 로 빠르게  처리하실 수 있습니다.</td></tr>

<tr><td height="8"></td></tr>
<tr><td><span class="def1"><b>- 카드결제주문은 아래와 같은 경우가 발생할 수 있습니다. (필독하세요!) -</span></td></tr>

<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>해당 PG사 관리자모드에는 승인이 되었으나, 주문리스트에서 주문상태가 '입금확인'이 아닌 '결제시도'로 되어 있는 경우가 발생될 수 있습니다.</td></tr>
<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>이는 중간에 통신상의 문제로 리턴값을 제대로 받지 못해 주문상태가 변경이 되지 않은 것입니다.</td></tr>
<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>즉, 이와같이 승인이 되었지만 주문상태가 '결제시도'인 경우 해당주문건의 주문상세내역 페이지에서 "결제시도, 실패 복원" 처리를 하시면 주문처리상태가 "입금확인"으로 수정됩니다.</td></tr>
<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>그러나 정상적인 리턴값을 받아 주문처리상태가 변경된 건이기에 이에 대해서는 정확한 결제로그를 주문상세내역페이지에서 확인을 할 수 없습니다.</td></tr>
<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>또한 고객이 카드결제로 주문을 1건 결제했는데 간혹 PG사 쪽에서는 2건이 승인(중복승인)되는 경우가 있습니다.</td></tr>
<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>이 경우는 해당 PG사의 관리자모드로 가서 중복승인된 2건중에 1건을 승인취소 해주시면 됩니다.</td></tr>
<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>중복승인건을 체크해서 바로 승인취소처리하지 않으면 미수금이 발생되어 쌓이게 되고, 해당 PG사로부터 거래중지요청 등의 불이익을 받을 수 있습니다.</td></tr>
<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>결제승인건의 주문상태와 중복승인건 처리는 세심하게 체크해야 하며 이에 대한 책임은 쇼핑몰 운영자에게 있습니다.</td></tr>
<tr><td><img src="../img/icon_list.gif" align="absmiddle"/>항상 카드결제건은 이곳 주문리스트와 PG사에서 제공하는 관리페이지의 결제승인건과 비교하면서 주의깊게 체크하여 처리하시기 바랍니다.</td></tr>
</table>
</div>
<script>cssRound('MSG01')</script>

<?
include "_deliveryForm.php"; //송장일괄입력폼
?>

<script>window.onload = function(){ UNM.inner();};</script>
<? @include dirname(__FILE__) . "/../interpark/_order_list.php"; // 인터파크_인클루드 ?>

<? include "../_footer.php"; ?>