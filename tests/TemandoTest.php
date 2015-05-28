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
    protected function getItemList($count)
    {
        $itemList = new \DVDoug\BoxPacker\ItemList;
        for ($i=0;$i<$count;$i++) {
            $box = new TestItem(
                'Le long box', 
                self::BOX_WIDTH,
                self::BOX_LENGTH,
                self::BOX_HEIGHT,
                self::BOX_WEIGHT
            );
            /* $box = new StdItem( */
            /*     "test box", */
            /*     self::BOX_WIDTH, */
            /*     self::BOX_LENGTH, */
            /*     self::BOX_HEIGHT, */
            /*     0, */
            /*     self::BOX_WIDTH, */
            /*     self::BOX_LENGTH, */
            /*     self::BOX_HEIGHT, */
            /*     self::BOX_WEIGHT */
            /* ); */
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
        $service = new TestBox(
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
        $packer = new Packer();
        $packer->setAllowPartialResults(true);
        $packed = $packer->packBox($service, $itemList);
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
        $service = new TestBox(
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
        $packer = new Packer();
        $packer->setAllowPartialResults(true);
        $packed = $packer->packBox($service, $itemList);
        $this->assertEquals(
            1,
            $packed->count(),
            "Should have been able to fit maximum 1 "
            . "items in this service box."
        );
    }

  /**
     * We expect this test to fail to pack all the boxes. More specifically,
     * given our Box/Service dimensions we expect two boxes to fit in here...
     */
    public function testTooManyItemsToFit()
    {
        $mod = 5;
        $service = new TestBox(
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
        $packer = new Packer();
        $packer->setAllowPartialResults(true);
        $packed = $packer->packBox($service, $itemList);
        $this->assertEquals(
            2,
            $packed->count(),
            "Should have been able to fit maximum 2 "
            . "items in this service box."
        );
    }

    public function testReasonableFit()
    {
        $mod = 1;
        $service = new TestBox(
            "Ten Item Service",
            self::SERVICE_WIDTH / $mod,
            self::SERVICE_LENGTH / $mod,
            self::SERVICE_HEIGHT / $mod,
            0,
            self::SERVICE_WIDTH / $mod,
            self::SERVICE_LENGTH / $mod,
            self::SERVICE_HEIGHT / $mod,
            self::SERVICE_WEIGHT
        );
        $itemList = $this->getItemList(8);
        $packer = new Packer();
        $packer->setAllowPartialResults(true);
        $packed = $packer->packBox($service, $itemList);
        $this->assertEquals(
            8,
            $packed->count(),
            "Should have been able to fit a maximum of 8 "
            . "items in this service box."
        );
    }

    /**
     * We expect this test to fail to pack all the boxes. More specifically,
     * given our Box/Service dimensions we expect 5 boxes to fit in here...
     */
    public function testExactMaxItemFit()
    {
        $mod = 2;
        $service = new TestBox(
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
        $itemList = $this->getItemList(5);
        $expected = $itemList->count() / $mod;
        $packer = new Packer();
        $packer->setAllowPartialResults(true);
        $packed = $packer->packBox($service, $itemList);
        $this->assertEquals(
            5,
            $packed->count(),
            "Should have been able to fit a maximum of 5 "
            . "items in this service box."
        );
    }


  /**
     * We expect this test to fail to pack all the boxes. More specifically,
     * given our Box/Service dimensions we expect 5 boxes to fit in here...
     */
    public function testEasyFit()
    {
        $mod = 1;
        $service = new TestBox(
            "10 Item Service",
            self::SERVICE_WIDTH / $mod,
            self::SERVICE_LENGTH / $mod,
            self::SERVICE_HEIGHT / $mod,
            0,
            self::SERVICE_WIDTH / $mod,
            self::SERVICE_LENGTH / $mod,
            self::SERVICE_HEIGHT / $mod,
            self::SERVICE_WEIGHT
        );
        $itemList = $this->getItemList(1);
        $expected = $itemList->count() / $mod;
        $packer = new Packer();
        $packer->setAllowPartialResults(true);
        $packed = $packer->packBox($service, $itemList);
        $this->assertEquals(
            1,
            $packed->count(),
            "Should have been able to fit a maximum of 10 "
            . "items in this service box."
        );
    }

}
