<?php 

namespace DVDoug\BoxPacker;

require "StdBox.php";
require "StdItem.php";


/* 
 * performs a bunch of additional tests to verify the correctness of the
 * box packer implimentation 
 */
class TemandoTest extends \PHPUnit_Framework_TestCase
{

    const SERVICE_WIDTH = 1000;
    const SERVICE_HEIGHT = 1000;
    const SERVICE_LENGTH = 1000;
    const SERVICE_WEIGHT = 1000;

    const BOX_WIDTH = 100;
    const BOX_HEIGHT = 100;
    const BOX_LENGTH = 100;
    const BOX_WEIGHT = 100;
    
    const LIST_LENGTH = 10;

    protected $packer;
    
    /** 
     * returns an ItemList object with the given 
     * amount of items initialised in it 
     *
     * @returns DVDoug\BoxPacker\ItemList
     */
    public function getItemList($count)
    {
        $itemList = new \DVDoug\BoxPacker\ItemList;
        for ($i=0;$i<$count;$i++) {
            $box = new StdItem(
                "test box",
                self::BOX_WIDTH,
                self::BOX_LENGTH,
                self::BOX_HEIGHT,
                self::BOX_WEIGHT
            );
            $itemList->insert($box);
        }
        return $itemList;
    }

    public function setUp()
    {
        $this->packer = new \DVDoug\BoxPacker\Packer();
        $this->packer->setAllowPartialResults(true);
    }

    /**
    * we expect this function to successfully pack all the items into
    * its box.
    */
    public function testPackSuccess()
    {
        $service = new StdBox(
            "One Item Service",
            self::SERVICE_WIDTH,
            self::SERVICE_LENGTH,
            self::SERVICE_HEIGHT,
            0,
            self::SERVICE_WIDTH,
            self::SERVICE_LENGTH,
            self::SERVICE_HEIGHT,
            self::SERVICE_WEIGHT
        );
        $itemList = $this->getitemList(self::LIST_LENGTH);
        $packed = $this->packer->packBox($service, $itemList);
        $this->assertEquals(
            10,
            $packed->count(),
            "Could not pack item into box when it should have been able to fit."
        );
    }

    /**
     * We expect this test to fail to pack all the boxes. More specifically,
     * given our Box/Service dimensions we expect one box to fit in here...
     */
    public function testPackFailOne()
    {
        $mod = 10;
        $service = new StdBox(
            "One Item Service",
            self::SERVICE_WIDTH / $mod,
            self::SERVICE_LENGTH / $mod,
            self::SERVICE_HEIGHT / $mod,
            0,
            self::SERVICE_WIDTH / $mod,
            self::SERVICE_LENGTH / $mod,
            self::SERVICE_HEIGHT / $mod,
            self::SERVICE_WEIGHT
        );
        $itemList = $this->getItemList(self::LIST_LENGTH);
        $expected = $itemList->count() / $mod;
        $packed = $this->packer->packBox($service, $itemList);
        $this->assertEquals(
            $expected,
            $packed->count(),
            "Should have been able to fit maximum $expected "
            . "items in this service box."
        );
    }

  /**
     * We expect this test to fail to pack all the boxes. More specifically,
     * given our Box/Service dimensions we expect two boxes to fit in here...
     */
    public function testPackFailTwo()
    {
        $mod = 5;
        $service = new StdBox(
            "Two Item Service",
            self::SERVICE_WIDTH / $mod,
            self::SERVICE_LENGTH / $mod,
            self::SERVICE_HEIGHT / $mod,
            0,
            self::SERVICE_WIDTH / $mod,
            self::SERVICE_LENGTH / $mod,
            self::SERVICE_HEIGHT / $mod,
            self::SERVICE_WEIGHT
        );
        $itemList = $this->getItemList(10);
        $expected = $itemList->count() / $mod;
        $packed = $this->packer->packBox($service, $itemList);
        $this->assertEquals(
            $expected,
            $packed->count(),
            "Should have been able to fit maximum $expected "
            . "items in this service box."
        );
    }


  /**
     * We expect this test to fail to pack all the boxes. More specifically,
     * given our Box/Service dimensions we expect 5 boxes to fit in here...
     */
    public function testPackFailThree()
    {
        $mod = 2;
        $service = new StdBox(
            "Two Item Service",
            self::SERVICE_WIDTH / $mod,
            self::SERVICE_LENGTH / $mod,
            self::SERVICE_HEIGHT / $mod,
            0,
            self::SERVICE_WIDTH / $mod,
            self::SERVICE_LENGTH / $mod,
            self::SERVICE_HEIGHT / $mod,
            self::SERVICE_WEIGHT
        );
        $itemList = $this->getItemList(self::LIST_LENGTH);
        $expected = $itemList->count() / $mod;
        $packed = $this->packer->packBox($service, $itemList);
        $this->assertEquals(
            $expected,
            $packed->count(),
            "Should have been able to fit maximum $expected "
            . "items in this service box."
        );
    }

}
