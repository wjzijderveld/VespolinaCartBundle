<?php
/**
 * (c) 2012 Vespolina Project http://www.vespolina-project.org
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Vespolina\CartBundle\Tests;

use Doctrine\Bundle\MongoDBBundle\Tests\TestCase;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\MongoDB\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\Bundle\MongoDBBundle\Mapping\Driver\XmlDriver;
use Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Vespolina\CartBundle\Document\CartManager;
use Vespolina\CartBundle\Document\Cart;
use Vespolina\CartBundle\Handler\DefaultCartHandler;
use Vespolina\Cart\Pricing\DefaultCartPricingProvider;
use Vespolina\CartBundle\Tests\Fixtures\Document\Product;
use Vespolina\EventDispatcher\NullDispatcher;

/**
 * @author Daniel Kucharski <daniel@xerias.be>
 * @author Richard D Shank <develop@zestic.com>
 */
abstract class CartTestCommon extends TestCase
{
    protected $cartMgr;
    protected $dm;
    protected $pricingProvider;
    protected $storage;
    protected $session;

    public function setup()
    {
        $this->cartMgr = $this->createCartManager();
    }

    public function tearDown()
    {
        $collections = $this->dm->getDocumentCollections();
        foreach ($collections as $collection) {
            $collection->drop();
        }
    }

    protected function createCart($name = 'default')
    {
        $cart = $this->getMockForAbstractClass('Vespolina\CartBundle\Model\Cart', array($name));

        $sp = new \ReflectionProperty('Vespolina\CartBundle\Model\Cart', 'state');
        $sp->setAccessible(true);
        $sp->setValue($cart, Cart::STATE_OPEN);
        $sp->setAccessible(false);

        $pr = new \ReflectionProperty('Vespolina\CartBundle\Model\Cart', 'pricingSet');
        $pr->setAccessible(true);
        $pr->setValue($cart, $this->getPricingProvider()->createPricingSet());
        $pr->setAccessible(false);
        return $cart;
    }

    protected function createCartItem($product)
    {
        // todo: this should handle recurring interface
        $cartItem = $this->getMockForAbstractClass('Vespolina\CartBundle\Model\CartItem', array($product));
        $cartItem->setDescription($product->getName());

        if ($product instanceof RecurringInterface) {
            $irp = new \ReflectionProperty('Vespolina\CartBundle\Model\CartItem', 'isRecurring');
            $irp->setAccessible(true);
            $irp->setValue($cartItem, true);
            $irp->setAccessible(false);
        }

        //Pricing
        $prrp = new \ReflectionProperty('Vespolina\CartBundle\Model\CartItem', 'pricingSet');
        $prrp->setAccessible(true);
        $prrp->setValue($cartItem, $this->getPricingProvider()->createPricingSet());
        $prrp->setAccessible(false);

        return $cartItem;
    }

    protected function createCartManager()
    {
        $pricingProvider = new DefaultCartPricingProvider();

        $this->storage = new MockArraySessionStorage();
        $this->session = new Session($this->storage, new AttributeBag(), new FlashBag());

        $this->dm = self::createTestDocumentManager();
        return new CartManager(
            $this->dm,
            $this->session,
            $pricingProvider,
            'Vespolina\CartBundle\Document\Cart',
            'Vespolina\CartBundle\Document\CartItem',
            'Vespolina\Entity\Order\CartEvents',
            new NullDispatcher()
        );
    }

    protected function createProduct($name, $price = null)
    {
        $product = new Product();
        $product->setName($name);
        if ($price && true === false) { // todo: get check on pricing for products
            $product->setPricing(array('unitPrice' => $price));
        }

        return $product;
    }

    protected function getPricingProvider()
    {
        if (!$this->pricingProvider) {

            $this->pricingProvider = new DefaultCartPricingProvider();

            $this->pricingProvider->addCartHandler(new DefaultCartHandler());
        }

        return $this->pricingProvider;
    }

    protected function buildLoadedCart($name, $nonRecurringItems, $recurringItems = 0)
    {
        $itemNames = array('alpha', 'beta', 'gamma', 'delta', 'epsilon', 'zeta', 'eta', 'theta');

        if ($nonRecurringItems > 8 || $recurringItems > 8) {
            throw new \Exception('Really? You need more than 8 items?
            If you really do add more, add more letters to the $itemNames array in CartTestCommon::buildLoadedCart(),
            update the test for max items and put in a PR.');
        }

        $cart = $this->createCart($name);
        for ($i = 0; $i < $nonRecurringItems ; $i++) {
            $cartItem = $this->createCartableItem($itemNames[$i], $i+1);
            $this->addItemToCart($cart, $cartItem);
        }
        for ($i = 0; $i < $recurringItems ; $i++) {
            $cartItem = $this->createRecurringCartableItem('recurring-'.$itemNames[$i], $i+1);
            $this->addItemToCart($cart, $cartItem);
        }

        $this->getPricingProvider()->determineCartPrices($cart);

        return $cart;
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
            __DIR__ . '/../Resources/config/doctrine' => 'Vespolina\\CartBundle\\Document',
        ), $paths);
        $config = new \Doctrine\ODM\MongoDB\Configuration();
        $config->setAutoGenerateProxyClasses(true);
        $config->setProxyDir(\sys_get_temp_dir());
        $config->setHydratorDir(\sys_get_temp_dir());
        $config->setProxyNamespace('SymfonyTests\Doctrine');
        $config->setHydratorNamespace('SymfonyTests\Doctrine');

        $xmlDriver = new XmlDriver($paths, '.mongodb.xml');
        $xmlDriver->setGlobalBasename('mapping');

        $chain = new MappingDriverChain();
        $chain->addDriver($xmlDriver, 'Vespolina\\CartBundle\\Document');

        AnnotationDriver::registerAnnotationClasses();
        $reader = new \Doctrine\Common\Annotations\AnnotationReader();
        $annotationDriver = new \Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver($reader, '/Fixtures/config/doctrine');
        $chain->addDriver($annotationDriver, 'Vespolina\\CartBundle\\Tests\\Fixtures\\Document');

        $config->setMetadataDriverImpl($chain);
        $config->setMetadataCacheImpl(new \Doctrine\Common\Cache\ArrayCache());

        return DocumentManager::create(new Connection(), $config);
    }
}
