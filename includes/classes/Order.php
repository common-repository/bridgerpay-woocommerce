<?php
namespace Bridgerpay;

class Order {
    protected $order_id = 0;
    protected $country = 'DE';
    protected $state = 'DE';
    protected $currency = 'EUR';
    protected $phone;
    protected $zip_code;
    protected $city;
    protected $address;
    protected $currency_lock = true;
    protected $amount_lock = true;
    protected $email;
    protected $last_name;
    protected $first_name;
    protected $amount;

    public function setId($order_id) {
        $this->order_id = $order_id;
    }

    public function setCountry($country) {
        $this->country = $country;
    }

    public function setState($state) {
        $this->state = $state;
    }

    public function setCurrency($currency) {
        $this->currency = $currency;
    }

    public function setPhone($phone) {
        $this->phone = $phone;
    }

    public function setZipCpde($postcode) {
        $this->zip_code = $postcode;
    }

    public function setCity($city) {
        $this->city = $city;
    }
    public function setAddress($address) {
        $this->address = $address;
    }

    public function setAmountLock($lock) {
        $this->amount_lock = $lock;
    }

    public function setCurrencyLock($lock) {
        $this->currency_lock = $lock;
    }

    public function setEmail($email) {
        $this->email = $email;
    }

    public function setLastName($name) {
        $this->last_name = $name;
    }

    public function setFirstName($name) {
        $this->first_name = $name;
    }

    public function setAmount($amount) {
        $this->amount = $amount;
    }

    public function getId() {
        return $this->order_id;
    }

    public function getCountry() {
        return $this->country;
    }

    public function getCurrency() {
        return $this->currency;
    }

    public function getState() {
        return $this->state;
    }

    public function getPhone() {
        return $this->phone;
    }

    public function getZipCode() {
        return $this->zip_code;
    }

    public function getCity() {
        return $this->city;
    }

    public function getAddress() {
        return $this->address;
    }

    public function getAmountLock() {
        return $this->amount_lock;
    }

    public function getCurrencyLock() {
        return $this->currency_lock;
    }

    public function getEmail() {
        return $this->email;
    }

    public function getLastName() {
        return $this->last_name;
    }

    public function getFirstName() {
        return $this->first_name;
    }

    public function getAmount() {
        return $this->amount;
    }

}