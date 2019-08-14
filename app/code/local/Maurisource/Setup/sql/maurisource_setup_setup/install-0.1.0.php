<?php
/**
 * Created by PhpStorm.
 * User: Parvesh
 * Date: 12/2/15
 * Time: 10:30 PM
 */ 
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();
$countryCode = 'CA';
//truncate tax tables
$connectionObject = $installer->getConnection();
$connectionObject->truncateTable($installer->getTable('tax_calculation_rate'));
$connectionObject->truncateTable($installer->getTable('tax_calculation_rule'));

//get the product tax class
$productTaxClass = Mage::getModel('tax/class')
    ->getCollection()
    ->addFieldToFilter('class_name', 'Taxable Goods')
    ->load()
    ->getFirstItem();

$productTaxShippingClass = Mage::getModel('tax/class')
    ->getCollection()
    ->addFieldToFilter('class_name', 'Shipping')
    ->load()
    ->getFirstItem();

//get the customer tax class
$customerTaxClass = Mage::getModel('tax/class')
    ->getCollection()
    ->addFieldToFilter('class_name', 'Retail Customer')
    ->load()
    ->getFirstItem();

$regionData = array();
$regionArray = Mage::getModel('directory/region_api')->items($countryCode);
foreach($regionArray as $region){
    $regionData[$region['region_id']] = $region['code'];
}

$correctedRegionData = array_flip($regionData);

$taxRates = array(
    array("GST","CA","*","*",5.00),
    array("PST-BC","CA",$correctedRegionData["BC"],"*",7.00),
    array("PST-MB","CA",$correctedRegionData["MB"],"*",8.0),
    array("HST-NB","CA",$correctedRegionData["NB"],"*",8.00),
    array("HST-NF","CA",$correctedRegionData["NL"],"*",8.00),
    array("HST-NS","CA",$correctedRegionData["NS"],"*",10.00),
    array("PST-ON","CA",$correctedRegionData["ON"],"*",8.00),
    array("HST-PEI","CA",$correctedRegionData["PE"],"*",9.00),
    array("TVQ","CA",$correctedRegionData["QC"],"*",9.975),
    array("PST-SAS","CA",$correctedRegionData["SK"],"*",5.00),
);


$taxRateArray = array();

foreach($taxRates as $taxRate){
    $taxCalculationRateId = Mage::getModel('tax/calculation_rate')
        ->setData(array(
            "code"                  => $taxRate[0],
            "tax_country_id"        => $taxRate[1],
            "tax_region_id"         => $taxRate[2],
            "tax_postcode"          => $taxRate[3],
            "rate"                  => $taxRate[4],
        ))->save()->getId();

    $taxRateArray[$taxCalculationRateId] = $taxRate[0];
}

$correctedTaxArray = array_flip($taxRateArray);

$taxRules = array(
    array("Taxable Goods Canada", $customerTaxClass->getId(), array($productTaxClass->getId(), $productTaxShippingClass->getId()), array($correctedTaxArray["GST"],$correctedTaxArray["HST-NB"],$correctedTaxArray["HST-NF"], $correctedTaxArray["HST-NS"], $correctedTaxArray["HST-PEI"]), 1, null,  1),
    array("Taxable Goods PST Ontario", $customerTaxClass->getId(), array($productTaxClass->getId()), array($correctedTaxArray["PST-ON"]), 1, null, 2),
    array("Taxable Goods PST MB", $customerTaxClass->getId(), array($productTaxClass->getId()), array($correctedTaxArray["PST-MB"]), 1, null, 4),
    array("Taxable Goods PST BC", $customerTaxClass->getId(), array($productTaxClass->getId()), array($correctedTaxArray["PST-BC"]), 1, null, 6),
    array("Taxable Goods PST SAS", $customerTaxClass->getId(), array($productTaxClass->getId()), array($correctedTaxArray["PST-SAS"]), 1, null, 8),
    array("Taxable Goods PST Quebec", $customerTaxClass->getId(), array($productTaxClass->getId(), $productTaxShippingClass->getId()), array($correctedTaxArray["TVQ"]), 2, 1, 3)
);


foreach($taxRules as $taxRule){
    $ruleModel = Mage::getModel('tax/calculation_rule')->setData(array(
        'code'               => $taxRule[0],
        'priority'           => $taxRule[4],
        'position'           => $taxRule[6],
        'tax_customer_class' => array($taxRule[1]),
        'tax_product_class'  => $taxRule[2],
        'tax_rate'           => $taxRule[3],
        'calculate_subtotal' => $taxRule[5]
    ))->save();

}

$installer->endSetup();