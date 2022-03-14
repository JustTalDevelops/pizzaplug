<?php

namespace Tal\PizzaPlug;

use Threaded;

class User extends Threaded
{

    public string $city;
    public string $streetNumber;
    public string $streetName;
    public string $unitType;
    public string $unitNumber;
    public string $postalCode;
    public string $countyNumber;
    public string $countyName;

    public function __construct(
        public string $firstName,
        public string $lastName,

        public string $email,
        public string $phoneNumber,

        public string $street,
        public string $region,
    ){}

}