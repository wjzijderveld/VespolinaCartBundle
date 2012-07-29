<?php
/**
 * (c) Vespolina Project http://www.vespolina-project.org
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Vespolina\CartBundle\Tests\Functional;

use Doctrine\Bundle\MongoDBBundle\Tests\TestCase;
use Doctrine\MongoDB\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\Bundle\MongoDBBundle\Mapping\Driver\XmlDriver;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Vespolina\CartBundle\Document\CartManager;
use Vespolina\Cart\Pricing\DefaultCartPricingProvider;
use Vespolina\CartBundle\Tests\CartTestCommon;
use Vespolina\CartBundle\Tests\Fixtures\Document\Person;
use Vespolina\CartBundle\Tests\Fixtures\Document\Product;
use Vespolina\EventDispatcher\NullDispatcher;


/**
 * @author Richard D Shank <develop@zestic.com>
 */
class CartManagerTest extends TestCase
{
    protected $cartMgr;
    protected $container;
    protected $storage;
    protected $session;

    public function testAddProductToCart()
    {
        $cart = $this->persistNewCart();
        $product = new Product();
        $product->setName('product1');
        $this->cartMgr->addProductToCart($cart, $product);
        $this->cartMgr->updateCart($cart);

        $items = $cart->getItems();
        $this->assertSame(1, $items->count());
        $item = $items->current();
        $this->assertSame($cartable, $item->getCartableItem());
        $this->assertSame(1, $item->getQuantity());

        $existingItem = $this->cartMgr->addItemToCart($cart, $cartable);
        $items = $cart->getItems();
        $this->assertSame(1, $items->count());
        $this->assertSame(2, $existingItem->getQuantity());
    }

    public function testUpdateCart()
    {
        $this->markTestIncomplete('todo');
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
        $this->assertNull($this->cartMgr->findOpenCartByOwner($owner));

        return $cart;
    }

    public function testGetActiveCartForOwner()
    {
        $owner = new Person('person');

        $this->dm->persist($owner);
        $this->dm->flush();

        $session = $this->container->get('session');
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
        $session = $this->container->get('session');
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
        $pricingProvider = new DefaultCartPricingProvider();

        $this->storage = new MockArraySessionStorage();
        $this->session = new Session($this->storage, new AttributeBag(), new FlashBag());

        $this->dm = self::createTestDocumentManager();
        $this->cartMgr = new CartManager(
            $this->dm,
            $this->session,
            $pricingProvider,
            'Vespolina\CartBundle\Document\Cart',
            'Vespolina\CartBundle\Document\CartItem',
            'Vespolina\Cart\Event\CartEvents',
            'Vespolina\EventDispatcher\Event',
            new NullDispatcher()
        );
    }

    public function tearDown()
    {
        $collections = $this->dm->getDocumentCollections();
        foreach ($collections as $collection) {
            $collection->drop();
        }
    }

    protected function persistNewCart($name = null)
    {
        $cart = $this->cartMgr->createCart($name);
        $this->cartMgr->updateCart($cart);

        return $cart;
    }

    /**
     * @return DocumentManager
     */
    public static function createTestDocumentManager($paths = array())
    {
        $paths = array_merge(array(
            __DIR__ . '/../../Resources/config/doctrine' => 'Vespolina\\CartBundle\\Document',
        ), $paths);
        $config = new \Doctrine\ODM\MongoDB\Configuration();
        $config->setAutoGenerateProxyClasses(true);
        $config->setProxyDir(\sys_get_temp_dir());
        $config->setHydratorDir(\sys_get_temp_dir());
        $config->setProxyNamespace('SymfonyTests\Doctrine');
        $config->setHydratorNamespace('SymfonyTests\Doctrine');
        $xmlDriver = new XmlDriver($paths, '.mongodb.xml');
        $config->setMetadataDriverImpl($xmlDriver);
        $config->setMetadataCacheImpl(new \Doctrine\Common\Cache\ArrayCache());

        return DocumentManager::create(new Connection(), $config);
    }
}