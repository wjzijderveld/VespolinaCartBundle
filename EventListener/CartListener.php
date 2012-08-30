<?php
/**
 * (c) Vespolina Project http://www.vespolina-project.org
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Vespolina\CartBundle\EventListener;

use Symfony\Component\DependencyInjection\Container;
use Vespolina\EventDispatcher\EventInterface;
use Vespolina\Entity\OrderInterface;

class CartListener
{
    protected $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function onUpdateCart(EventInterface $event)
    {
        $cart = $event->getSubject();

        $this->container->get('vespolina.pricing_provider')->determineCartPrices($cart);
    }

    protected function getMailBody(OrderInterface $salesOrder)
    {
        // @TODO: Make template configurable
        $twig = $this->container->get('twig');
        return $twig->render('VespolinaCartBundle:Email:checkout_complete.html.twig', array('order' => $salesOrder));
    }

}