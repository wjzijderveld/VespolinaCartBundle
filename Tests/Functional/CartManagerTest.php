<?php
/**
 * (c) Vespolina Project http://www.vespolina-project.org
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Vespolina\CartBundle\Tests\Functional;



use Symfony\Component\Finder\Finder;

use Vespolina\CartBundle\Document\Cart;
use Vespolina\CartBundle\Tests\CartTestCommon;
use Vespolina\CartBundle\Tests\Fixtures\Document\Person;
use Vespolina\CartBundle\Tests\Fixtures\Document\Product;


/**
 * @author Richard D Shank <develop@zestic.com>
 */
class CartManagerTest extends CartTestCommon
{
    protected $cartMgr;
    protected $container;
    protected $dm;
    protected $storage;
    protected $session;

    public function testAddProductToCart()
    {
        $cart = $this->persistNewCart();
        $product = new Product();
        $product->setName('product1');
        $this->dm->persist($product);
        $this->dm->flush();

        $this->cartMgr->addProductToCart($cart, $product);
        $this->cartMgr->updateCart($cart);
        $cartId = $cart->getId();

        $this->dm->detach($cart);

        $loadedCart = $this->cartMgr->findCartById($cartId);
        $items = $loadedCart->getItems();
        $this->assertSame(1, $items->count());
        $item = $items[0];
        $loadedProduct = $item->getProduct();
        $productName = $loadedProduct->getName();
        $this->assertSame('product1', $loadedProduct->getName());
        $this->assertEquals(1, $item->getQuantity());
    }

    public function testFindOpenCartByOwner()
    {
        $owner = new Person('person');

        $this->dm->persist($owner);
        $this->dm->flush();

        $cart = $this->cartMgr->createCart();
        $cart->setOwner($owner);
        $this->cartMgr->updateCart($cart);

        $ownersCart = $this->cartMgr->findOpenCartByOwner($owner);
        $this->assertSame($cart->getId(), $ownersCart->getId());

        $this->cartMgr->setCartState($cart, Cart::STATE_CLOSED);
        $this->cartMgr->updateCart($cart);
        $this->assertNull($this->cartMgr->findOpenCartByOwner($owner));

        return $cart;
    }

    public function testGetActiveCartForOwner()
    {
        $owner = new Person('person');

        $this->dm->persist($owner);
        $this->dm->flush();

        $session = $this->session;
        // not really a test, but it does make sure we start empty
        $this->assertNull($session->get('vespolina_cart'));

        $firstPassCart = $this->cartMgr->getActiveCart($owner);
        $persistedCarts = $this->cartMgr->findBy(array());
        $this->assertSame(1, $persistedCarts->count(), 'there should only be one cart in the db');
        $this->assertSame($firstPassCart, $session->get('vespolina_cart'), 'the new cart should have been set for the session');

        $secondPassCart = $this->cartMgr->getActiveCart($owner);
        $this->assertSame($firstPassCart->getId(), $secondPassCart->getId());
        $this->assertSame(1, $persistedCarts->count(), 'there should only be one cart in the db');

        $session->clear('vespolina_cart');
        $thirdPassCart = $this->cartMgr->getActiveCart($owner);
        $this->assertSame($firstPassCart->getId(), $thirdPassCart->getId());
        $this->assertSame(1, $persistedCarts->count(), 'there should only be one cart in the db');
        $this->assertSame($thirdPassCart, $session->get('vespolina_cart'), 'the new cart should have been set for the session');
    }


    public function testGetActiveCartWithoutOwner()
    {
        $session = $this->session;
        // not really a test, but it does make sure we start empty
        $this->assertNull($session->get('vespolina_cart'));

        $firstPassCart = $this->cartMgr->getActiveCart();
        $persistedCarts = $this->cartMgr->findBy(array());
        $this->assertSame(1, $persistedCarts->count(), 'there should only be one cart in the db');
        $this->assertSame($firstPassCart, $session->get('vespolina_cart'), 'the new cart should have been set for the session');

        $secondPassCart = $this->cartMgr->getActiveCart();
        $this->assertSame($firstPassCart->getId(), $secondPassCart->getId());
        $this->assertSame(1, $persistedCarts->count(), 'there should only be one cart in the db');

        $session->clear('vespolina_cart');
        $thirdPassCart = $this->cartMgr->getActiveCart();
        $this->assertNotSame($firstPassCart->getId(), $thirdPassCart->getId());
        $this->assertSame(2, $persistedCarts->count(), 'there is a left over cart, this should probably be handled');
        $this->assertSame($thirdPassCart, $session->get('vespolina_cart'), 'the new cart should have been set for the session');
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
