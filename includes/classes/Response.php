<?php
namespace Bridgerpay;

class Response {
    protected $type;
    protected $order_id;
    protected $charge_id;
    protected $psp_order_id;
    protected $psp_name;
    protected $attributes = array();
    protected $metadata = array();
    protected $is_refundable;
    protected $refund_id;
    protected $errors;
    protected $message;

    public function __construct($data)
    {
        $this->type = $data['webhook']['type'];
        $this->order_id = $data['data']['order_id'];
        $this->psp_name = $data['data']['psp_name'];
        $this->charge_id = $data['data']['charge']['id'];
        $this->psp_order_id = $data['data']['charge']['psp_order_id'];
        $this->attributes = $data['data']['charge']['attributes'];
        $this->is_refundable = (boolean)$data['data']['charge']['is_refundable'];
        $this->refund_id = $data['data']['charge']['refund_id'];
        $this->psp_order_id = $data['data']['charge']['psp_order_id'];
        $this->metadata = $data['meta'];
    }

    public function getType() {
        return $this->type;
    }

    public function getOrderId() {
        return $this->order_id;
    }

    public function getChargeId() {
        return $this->charge_id;
    }

    public function getRefundId() {
        return $this->refund_id;
    }

    public function getMessage() {
        return $this->message;
    }

    public function isComplete () {

        return $this->type == "approved" ? true : false;
    }

    public function isRefundable () {

        return $this->is_refundable;
    }
}