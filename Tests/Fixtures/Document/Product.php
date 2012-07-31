<?php
/**
 * (c) 2011 - 2012 Vespolina Project http://www.vespolina-project.org
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Vespolina\CartBundle\Tests\Fixtures\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;

use Vespolina\Entity\Product as CoreProduct;

/**
 * @author Daniel Kucharski <daniel@xerias.be>
 * @author Richard Shank <develop@zestic.com>
 */
/**
 * @ODM\Document(collection="product")
 */
class Product extends CoreProduct
{
    /** @ODM\Id */
    protected $id;

    /** @ODM\String */
    protected $name;


    public function __construct()
    {
       $this->prices = array();
    }

    public function setId($id)
    {
        $this->id = $id;
    }
}
