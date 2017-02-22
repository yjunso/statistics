<?php
/**
 * �ֹ�����Ʈ���� ���̹�üũ�ƿ� �ֹ�������
 * @author sunny, oneorzero
 */

/*
 * CheckoutAPI ȯ�溯��
*/
$config = Core::loader('config');
$checkoutapi = $config->load('checkoutapi');

/*
 * CheckoutAPI �ֹ� ���� ���հ����� ����ϴ� ���
*/
if(!($checkoutapi['cryptkey'] && $checkoutapi['integrateOrder']=='y')) return false;	// include �̹Ƿ� return �ص� ��..

// üũ�ƿ� 4.0 ����
$checkout_message_schema = include "./_cfg.checkout.php";

$checkout_arWhere = array();
$checkout_isUnableCondition=false;

if($search['regdt_start']) {
	if(!$search['regdt_end']) $search['regdt_end'] = date('Ymd');
	$tmp_start = substr($search['regdt_start'],0,4).'-'.substr($search['regdt_start'],4,2).'-'.substr($search['regdt_start'],6,2).' 00:00:00';
	$tmp_end = substr($search['regdt_end'],0,4).'-'.substr($search['regdt_end'],4,2).'-'.substr($search['regdt_end'],6,2).' 23:59:59';
	switch($search['dtkind']) {
		case 'orddt': $checkout_arWhere[] = $db->_query_print('O.OrderDate between [s] and [s]',$tmp_start,$tmp_end); break;
		case 'cdt': $checkout_arWhere[] = $db->_query_print('O.PaymentDate between [s] and [s]',$tmp_start,$tmp_end); break;
		case 'ddt': $checkout_arWhere[] = $db->_query_print('D.SendDate between [s] and [s]',$tmp_start,$tmp_end); break;
		case 'confirmdt': $checkout_arWhere[] = $db->_query_print('D.DeliveredDate between [s] and [s]',$tmp_start,$tmp_end); break;
	}
}

if($search['settlekind']) {
	$tmpMap = array('a'=>'�������Ա�','c'=>'�ſ�ī��','o'=>'������ü');
	if(array_key_exists($search['settlekind'],$tmpMap)) {
		$checkout_arWhere[] = $db->_query_print('O.PaymentMeans = [s]',$tmpMap[$search['settlekind']]);
	}
	else {
		$checkout_isUnableCondition=true;
	}
}

// �ֹ� ���º� �˻���
$tmp_checkout_arWhere = array();
if(sizeof($search['step']) > 0) { foreach ($search['step'] as $_step) {
	switch ((int)$_step) {
		case 0:	// �ֹ����� (�Աݴ��)
			$tmp_checkout_arWhere[] = "(PO.ProductOrderStatus = 'PAYMENT_WAITING' AND PO.ClaimStatus = '')";
			break;
		case 1:	// �Ա�Ȯ�� (����Ȯ����)
			$tmp_checkout_arWhere[] = "(PO.ProductOrderStatus = 'PAYED' AND PO.PlaceOrderStatus = 'NOT_YET' AND PO.ClaimStatus = '')";
			break;
		case 2:	// ����غ���(����Ȯ��)
			$tmp_checkout_arWhere[] = "(PO.ProductOrderStatus = 'PAYED' AND PO.PlaceOrderStatus = 'OK' AND PO.ClaimType = '')";
			break;
		case 3:	// �����
			$tmp_checkout_arWhere[] = "(PO.ProductOrderStatus = 'DELIVERING' AND PO.ClaimStatus = '')";
			break;
		case 4:	// ��ۿϷ�, ����Ȯ��
			$tmp_checkout_arWhere[] = "(PO.ClaimStatus = '' AND (PO.ProductOrderStatus = 'DELIVERED' OR PO.ProductOrderStatus = 'PURCHASE_DECIDED'))";
			break;
	}
}}

if(sizeof($search['step2']) > 0) { foreach ($search['step2'] as $_step) {
	switch ((int)$_step) {
		case 1:	// ��� (��ҿ�û, ���ó����, ��ҿϷ�)
			$tmp_checkout_arWhere[] = "PO.ClaimType = 'CANCEL'";
			break;
		case 2:
			// ȯ�� �ܰ� ����

			break;
		case 3:	// ��ǰ (��ǰ��û, ��ǰ������, ��ǰ���ſϷ�, ��ǰ�Ϸ�)
			$tmp_checkout_arWhere[] = "PO.ClaimType = 'RETURN'";
			break;
		case 60:// ��ȯ (��ȯ��û, ��ȯ������, ��ȯ���ſϷ�, ��ȯ������, ��ȯ�Ϸ�)
			$tmp_checkout_arWhere[] = "PO.ClaimType = 'EXCHANGE'";
			break;
	}
}}

if(sizeof($search['step']) > 0 || sizeof($search['step2']) > 0) {
	if(sizeof($tmp_checkout_arWhere) > 0) {
		$checkout_arWhere[] = '('.implode(' OR ',$tmp_checkout_arWhere).')';
	}
	else {
		$checkout_isUnableCondition=true;
	}
}

if($search['sword'] && $search['skey']) {
	$es_sword = $db->_escape($search['sword']);
	switch($search['skey']) {
		case 'all':
			$checkout_arWhere[] = "(
				O.OrderID = '{$es_sword}' or
				O.OrdererName like '%{$es_sword}%' or
				PO.ShippingAddressName like '%{$es_sword}%' or
				O.OrdererID = '{$es_sword}'
			)"; break;
		case 'ordno': $checkout_arWhere[] = "O.OrderID = '{$es_sword}'"; break;
		case 'nameOrder': $checkout_arWhere[] = "O.OrdererName like '%{$es_sword}%'"; break;
		case 'nameReceiver': $checkout_arWhere[] = "PO.ShippingAddressName like '%{$es_sword}%'"; break;
		case 'm_id': $checkout_arWhere[] = "O.OrdererID = '{$es_sword}'"; break;
	}
}
if($search['sgword'] && $search['sgkey']) {
	$es_sgword = $db->_escape($search['sgword']);
	switch($search['sgkey']) {
		case 'goodsnm': $checkout_arWhere[] = "PO.ProductName like '%{$es_sgword}%'"; break;
		default: $checkout_isUnableCondition=true;
	}
}

if($checkout_isUnableCondition) {
	$checkout_arWhere[] = '0';
}

if(count($checkout_arWhere)) {
	$checkout_strWhere = 'where '.implode(' and ',$checkout_arWhere);
}

if($search['mode']=='group') {
	$SQL_CALC_FOUND_ROWS='';
}
else {
	$SQL_CALC_FOUND_ROWS='SQL_CALC_FOUND_ROWS';
}

$query = '
	(
		select '.$SQL_CALC_FOUND_ROWS.'
			"godo" as _order_type,
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
			o.settleInflow as settleInflow,
			m.m_id as m_id,
			m.m_no as m_no,
			m.level as level,
			'.$select_count_item.',
			'.$select_count_dv_item.',
			oi.goodsnm as goodsnm,
			null AS PlaceOrderStatus,
			null AS ProductOrderStatus,
			null AS ClaimType,
			null AS ClaimStatus,
			null AS ProductOrderIDList

		from
			'.GD_ORDER.' as o
			left join '.GD_ORDER_ITEM.' as oi on o.ordno=oi.ordno
			left join '.GD_MEMBER.' as m on o.m_no = m.m_no
			'.$join_GD_COUPON_ORDER.'
		'.$strWhere.'
		group by o.ordno
	)
	union
	(
		select
			"checkout" as _order_type,
			O.OrderID as ordno,
			O.OrdererName as nameOrder,
			PO.ShippingAddressName as nameReceiver,
			O.PaymentMeans as settlekind,
			PO.ProductOrderStatus as step,
			null as step2,
			O.OrderDate as orddt,
			"n" as dyn,
			null as escrowyn,
			null as eggyn,
			null as inflow,
			null as deliverycode,
			null as cashreceipt,
			null as cbyn,
			null as oldordno,
			SUM(PO.TotalPaymentAmount) AS prn_settleprice,
			null as settleInflow,
			MB.m_id as m_id,
			MB.m_no as m_no,
			MB.level as level,
			COUNT(PO.ProductOrderID) AS count_item,
			null as count_dv_item,
			PO.ProductName as goodsnm,

			PO.PlaceOrderStatus,
			PO.ProductOrderStatus,
			PO.ClaimType,
			PO.ClaimStatus,
			GROUP_CONCAT(PO.ProductOrderID SEPARATOR ",") AS ProductOrderIDList

		FROM '.GD_NAVERCHECKOUT_ORDERINFO.' AS O

		INNER JOIN '.GD_NAVERCHECKOUT_PRODUCTORDERINFO.' AS PO
			ON PO.OrderID = O.OrderID

		LEFT JOIN '.GD_MEMBER.' AS MB
			ON PO.MallMemberID=MB.m_id

		LEFT JOIN '.GD_NAVERCHECKOUT_DELIVERYINFO.' AS D
			ON PO.ProductOrderID = D.ProductOrderID

		LEFT JOIN '.GD_NAVERCHECKOUT_CANCELINFO.' AS C
			ON PO.ProductOrderID = C.ProductOrderID

		LEFT JOIN '.GD_NAVERCHECKOUT_RETURNINFO.' AS R
			ON PO.ProductOrderID = R.ProductOrderID

		LEFT JOIN '.GD_NAVERCHECKOUT_EXCHANGEINFO.' AS E
			ON PO.ProductOrderID = E.ProductOrderID

		LEFT JOIN '.GD_NAVERCHECKOUT_DECISIONHOLDBACKINFO.' AS DH
			ON PO.ProductOrderID = DH.ProductOrderID

		'.$checkout_strWhere.'

		GROUP BY PO.OrderID, PO.ProductOrderStatus, PO.ClaimStatus
	)
';

if($search['mode']=='group') {
	$result = $db->_select($query);

	// �׷캰�� �ֹ��� �Ҵ�
	foreach($result as $v) {
		if($v['_order_type']=='godo') {
			$orderGroupKey = $v['step2']*10+($v['step'] === '1' || ($v['step'] === '2' && $v['step2'] > 40) ? 1 : $v['step']);
			$orderGroupNameMap[$orderGroupKey] = getStepMsg($v['step'],$v['step2']);

			$orderList[$orderGroupKey][] = $v;
		}
		elseif($v['_order_type']=='checkout') {
			$_step_msg = getCheckoutOrderStatus($v);
			$_step_key = array_search($_step_msg, $r_step);

			if ($_step_key === false || $_step_key === null)  {
				$orderGroupKey = $_step_msg;
			}
			else {
				$orderGroupKey = $_step_key;
			}

			$orderGroupNameMap[$orderGroupKey] = $_step_msg;
			$orderList[$orderGroupKey][] = $v;
		}
	}
	ksort($orderList);
	foreach($orderList as $orderGroupKey=>$eachOrderGroup) {
		$sortAssistDyn=$sortAssistOrddt=array();
		foreach ($eachOrderGroup as $k => $v) {
			$sortAssistDyn[$k]  = $v['dyn'];
			$sortAssistOrddt[$k] = $v['orddt'];
			if($v['_order_type']=='godo') {
				$orderList[$orderGroupKey][$k]['stepMsg'] = getStepMsg($v['step'],$v['step2'],$v['ordno']);
			}
		}
		array_multisort($sortAssistDyn,SORT_ASC,$sortAssistOrddt,SORT_DESC,$orderList[$orderGroupKey]);

		$i=0;
		foreach ($eachOrderGroup as $k => $v) {
			$orderList[$orderGroupKey][$k]['_rno'] = count($eachOrderGroup)-($i++);
		}
	}
}
else {
	if(!$cfg['orderPageNum']) $cfg['orderPageNum'] = 15;

	$query = $query.' order by orddt desc';
	$result = $db->_select_page($cfg['orderPageNum'],$page,$query);

	$orderList[9999]=array();
	foreach($result['record'] as $v) {
		if($v['_order_type']=='godo') {
			$v['stepMsg']=getStepMsg($v['step'],$v['step2'],$v['ordno']);
		}
		$orderList[9999][] = $v;
	}
	$pageNavi = $result['page'];
}
?>

<script type="text/javascript">
// ��۹��, �ù�� change �̺�Ʈ bind
document.observe("dom:loaded", function() {
	$$("select.el-DeliveryMethodCode").each(function(el){
		Event.observe(el, 'change', function(e) {
			var _el = event.srcElement;

			if (_el.value == 'DELIVERY') {
				_el.next('select',0).writeAttribute('disabled',false);	_el.next('input',0).writeAttribute('disabled',false);
			}
			else {
				_el.next('select',0).writeAttribute('disabled',true);	_el.next('input',0).writeAttribute('disabled',true);
			}
		});
	});
});

/**
* üũ�ƿ��� �ֹ����º������
*/
function processCheckoutOrder(f, selCase, isGodoChk) {

	// ó���� üũ�ƿ� �ֹ����� �ֳ���?
	if ($$('input[name^="PlaceProductOrder_OrderID"]:checked').size() > 0 || $$('input[name^="ShipProductOrder_OrderID"]:checked').size() > 0) {

		//
		var _sel = selCase.value + '';

		if (_sel == '2') {	// ����غ��� (����Ȯ��)

			// �ٸ��� ���� �ֳ���?
			if ($$('input[name^="ShipProductOrder_OrderID"]:checked').size() > 0) {
				alert('����غ���(����) ó�� �� �� ���� üũ�ƿ� �ֹ����� ���ԵǾ� �ֽ��ϴ�.');
				return false;
			}

		}
		else if (_sel == '3') {	// �����(�߼�)

			// �ٸ��� ���� �ֳ���?
			if ($$('input[name^="PlaceProductOrder_OrderID"]:checked').size() > 0) {
				alert('�����(�߼�) ó�� �� �� ���� üũ�ƿ� �ֹ����� ���ԵǾ� �ֽ��ϴ�.');
				return false;
			}

			// �����, ��۹��, �ù��, �����ȣ üũ.
			try
			{
				var idx = 0;
				$$('input.chk_ordno').each(function(el) {
					idx++;
					if (el.checked) {
						if ($$('input[name="DispatchDate['+idx+']"]').pop().value == '') throw '������� �Է��� �ּ���.';
						if ($$('select[name="DeliveryMethodCode['+idx+']"]').pop().value == '') throw '��۹���� ������ �ּ���.';

						if ($$('select[name="DeliveryMethodCode['+idx+']"]').pop().value == 'DELIVERY') {
							if ($$('select[name="DeliveryCompanyCode['+idx+']"]').pop().value == '') throw '�ù�縦 ������ �ּ���.';
							if ($$('input[name="TrackingNumber['+idx+']"]').pop().value == '') throw '�����ȣ�� �Է��� �ּ���.';
						}
					}
				});
			}
			catch (e) {
				if (typeof e == 'string') alert(e);
				return false;
			}
		}
		else {

			var _operation = '';

			switch (_sel) {
				case '0':
					_operation = '�ֹ�����';
					break;
				case '1':
					_operation = '�Ա�Ȯ��';
					break;
				case '4':
					_operation = '��ۿϷ�';
					break;
			}

			alert('üũ�ƿ� �ֹ��� ' + _operation + 'ó�� �� �� �����ϴ�.');
			return false;

		}

		var myAjax = new Ajax.Request("./ax.checkout.api.process.php",{
			"method":"post",
			"parameters":f.serialize(true),
			"onComplete":function(transport){

				if (transport.responseText != 'OPERATION_IS_NOT_FOUND') {
					alert(transport.responseText);
				}

				if(isGodoChk) {
					f.submit();
				}
				else {
					self.location.href=self.location.href;
				}
			}
		});

		return false;

		//
	}

	return true;

}
</script>

<?
return true;
?>
