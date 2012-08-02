<?php

namespace Vespolina\CartBundle\Tests;

use Vespolina\CartBundle\Tests\CartTestCommon;
use Vespolina\CartBundle\Tests\Fixtures\Document\Product;

use Vespolina\CartBundle\Document\Cart;

class CartProcessTest extends CartTestCommon
{
    protected $client;

    public function testProcessCart()
    {
        $this->markTestIncomplete('the pricing needs to be tested before this test can be useful');

        $customerId = '1248934893';

        $product1 = $this->createProduct('Ipad 2', 499);
        $product2 = $this->createProduct('Iphone 4S');
        $this->dm->persist($product1);
        $this->dm->persist($product2);
        $this->dm->flush();

        $cart = $this->cartMgr->createCart();

        $cart->setOwner($customerId);
        $cart->setExpiresAt(new \DateTime('now + 2 days'));

        $cartItem1 = $this->cartMgr->addProductToCart($cart, $product1);
        $this->cartMgr->setItemQuantity($cartItem1, 10);
        $cartItem1->getPricingSet()->set('unitPrice', 499);

        $cartItem1->addOption('color', 'white');
        $cartItem1->addOption('connectivity', 'WIFI+3G');
        $cartItem1->addOption('size', '64GB');

        $this->cartMgr->setItemQuantity($cartItem1, 3);
        $this->cartMgr->setCartItemState($cartItem1, 'init');

        $this->assertEquals($cartItem1->getName(), $product1->getName());

        $cartItem2 = $this->cartMgr->addItemToCart($cart, $product2);
        $this->cartMgr->setItemQuantity($cartItem2, 2);

        $cartItem2->getPricingSet()->set('unitPrice', 699);
        $this->cartMgr->setCartItemState($cartItem2, 'init');

        $testCartItem1 = $cart->getItem(1);

        $cartOwner = $cart->getOwner();
        $this->assertEquals($cartOwner, $customerId);

        //Calculate prices
        $this->cartMgr->determinePrices($cart);

        $this->cartMgr->updateCart($cart);

        //Step two, find back the open cart
        $aCart = $this->cartMgr->findOpenCartByOwner($customerId);
        $this->assertEquals(count($aCart->getItems()), 2);

        $aCartItem1 = $aCart->getItem(1);

        $this->assertEquals($aCartItem1->getPricingSet()->get('unitPriceTotal'), 499);
        $this->assertEquals($aCartItem1->getOption('color'), 'white');

        //...and close it
        $aCart->setFollowUp('sales_order_12093488');
        $this->cartMgr->setCartState($aCart, Cart::STATE_CLOSED);

        $this->cartMgr->updateCart($aCart, true);

        $aCart->clearItems();
        $this->assertEquals($aCart->getItems()->count(), 0);

    }

    public function setup()
    {
        parent::setup();
    }

    public function tearDown()
    {
        parent::tearDown();
    }
}