<?php
/**
 * Box packing (3D bin packing, knapsack problem)
 * @package BoxPacker
 * @author Doug Wright
 */
namespace DVDoug\BoxPacker;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

/**
 * Actual packer
 * @author Doug Wright
 * @package BoxPacker
 */
class Packer implements LoggerAwareInterface {
  use LoggerAwareTrait;

  /**
   * List of items to be packed
   * @var ItemList
   */
  protected $items;

  /**
   * List of box sizes available to pack items into
   * @var BoxList
   */
  protected $boxes;

  /**
   * If setAllowPartialResults is true this will return any items that don't fit
   * @var ItemList
   */
  protected $remainingItems;

  /**
   * If set this will allow remaining items to be returned
   * @var boolean
   */
  protected $allowPartialResults = false;

  protected $areBoxesLimited = false;

  /**
   * Constructor
   */
  public function __construct() {
    $this->items = new ItemList();
    $this->boxes = new BoxList();
    $this->remainingItems = new ItemList();

    $this->logger = new NullLogger();
  }

  /**
   * Add item to be packed
   * @param Item $aItem
   * @param int  $aQty
   */
  public function addItem(Item $aItem, $aQty = 1) {
    for ($i = 0; $i < $aQty; $i++) {
      $this->items->insert($aItem);
    }
    $this->logger->log(LogLevel::INFO, "added {$aQty} x {$aItem->getDescription()}");
  }

  /**
   * Set a list of items all at once
   * @param \Traversable $aItems
   */
  public function setItems($aItems) {
    if ($aItems instanceof ItemList) {
      $this->items = clone $aItems;
    }
    else if (is_array($aItems)) {
      $this->items = new ItemList();
      foreach ($aItems as $item) {
        $this->items->insert($item);
      }
    }
    else {
      throw new \RuntimeException('Not a valid list of items');
    }
  }

  /**
   * Add box size
   * @param Box $aBox
   */
  public function addBox(Box $aBox) {
    $this->boxes->insert($aBox);
    $this->logger->log(LogLevel::INFO, "added box {$aBox->getReference()}");
  }

  /**
   * Add a pre-prepared set of boxes all at once
   * @param BoxList $aBoxList
   */
  public function setBoxes(BoxList $aBoxList) {
    $this->boxes = clone $aBoxList;
  }

  /**
   * Set boolean if provided boxes are limited to the times they are provided
   *
   * @param bool $limited
   */
  public function setBoxesLimited($limited = true) {
    $this->areBoxesLimited = $limited;
  }

  /**
   * Boolean If building nests or not
   *
   * @return bool
   */
  public function areBoxesLimited() {
    return $this->areBoxesLimited;
  }

  /**
   * Pack items into boxes
   *
   * @throws \RuntimeException
   * @return PackedBoxList
   */
  public function pack() {
    if ($this->areBoxesLimited()) {
      $packedBoxes = $this->doVolumePackingWithLimitedBoxes();
    } else {
      $packedBoxes = $this->doVolumePacking();
    }

    //If we have multiple boxes, try and optimise/even-out weight distribution
    if ($packedBoxes->count() > 1) {
      $packedBoxes = $this->redistributeWeight($packedBoxes);
    }

    $this->logger->log(LogLevel::INFO, "packing completed, {$packedBoxes->count()} boxes");
    return $packedBoxes;
  }

  /**
   * Pack items into boxes using the principle of largest volume item first
   *
   * @throws \RuntimeException
   * @return PackedBoxList
   */
  public function doVolumePacking() {
    $packedBoxes = new PackedBoxList;
    //Keep going until everything packed
    while ($this->items->count()) {
      $boxesToEvaluate = clone $this->boxes;
      $packedBoxesIteration = new PackedBoxList;

      //Loop through boxes starting with smallest, see what happens
      while (!$boxesToEvaluate->isEmpty()) {
        $box = $boxesToEvaluate->extract();

        $packedBox = $this->packIntoBox($box, clone $this->items);

        if ($packedBox->getItems()->count()) {
          $packedBoxesIteration->insert($packedBox);

          //Have we found a single box that contains everything?
          if ($packedBox->getItems()->count() === $this->items->count()) {
            break;
          }
        }
      }

      //Check iteration was productive
      if ($packedBoxesIteration->isEmpty()) {
        throw new \RuntimeException('Item ' . $this->items->top()->getDescription() . ' is too large to fit into any box');
      }

      //Find best box of iteration, and remove packed items from unpacked list
      $bestBox = $packedBoxesIteration->top();
      for ($i = 0; $i < $bestBox->getItems()->count(); $i++) {
        $this->items->extract();
      }
      $packedBoxes->insert($bestBox);

    }

    return $packedBoxes;
  }

  public function doVolumePackingWithLimitedBoxes() {
    $packedBoxes = new PackedBoxList;
    if ($this->boxes->isEmpty()) {
      return $packedBoxes;
    }
    $boxesToEvaluate = $this->boxes;
    $isContainer = false;

    //Keep going until everything packed
    while ($this->items->count()) {
      $packedBoxesIteration = new PackedBoxList;
      //Loop through boxes starting with smallest, see what happens
      while (!$boxesToEvaluate->isEmpty()) {
        $box = $boxesToEvaluate->extract();

        // because we only build nests first and then refresh the packer - therefore if 1 box is container then all boxes are containers
        if ($box->getBoxType() === 'container') {
          $isContainer = true;
        }
        $packedBox = $this->packIntoBox($box, clone $this->items);

        if ($packedBox->getItems()->count()) {
          for ($i = 0; $i < $packedBox->getItems()->count(); $i++) {
            $this->items->extract();
          }
          $packedBoxesIteration->insert($packedBox);

          //Have we found a single box that contains everything?
          if ($packedBox->getItems()->count() === $this->items->count()) {
            break;
          }
        }
      }

      //Check iteration was productive
      if ($packedBoxesIteration->isEmpty()) {
        if ($isContainer) {
          return $packedBoxes;
        } else {
          throw new \RuntimeException('Item ' . $this->items->top()->getDescription() . ' is too large to fit into any box');
        }
      }

      //Find best box of iteration, and remove packed boxes from unpacked list
      while (!$packedBoxesIteration->isEmpty()) {
        $bestBox = $packedBoxesIteration->extract();
        $packedBoxes->insert($bestBox);
      }

    }

    return $packedBoxes;
  }

  /**
   * Given a solution set of packed boxes, repack them to achieve optimum weight distribution
   *
   * @param PackedBoxList $aPackedBoxes
   * @return PackedBoxList
   */
  public function redistributeWeight(PackedBoxList $aPackedBoxes) {
    $targetWeight = $aPackedBoxes->getMeanWeight();
    $this->logger->log(LogLevel::DEBUG,  "repacking for weight distribution, weight variance {$aPackedBoxes->getWeightVariance()}, target weight {$targetWeight}");

    $packedBoxes = new PackedBoxList;

    $overWeightBoxes = [];
    $underWeightBoxes = [];
    foreach ($aPackedBoxes as $packedBox) {
      $boxWeight = $packedBox->getWeight();
      if ($boxWeight > $targetWeight) {
        $overWeightBoxes[] = $packedBox;
      }
      else if ($boxWeight < $targetWeight) {
        $underWeightBoxes[] = $packedBox;
      }
      else {
        $packedBoxes->insert($packedBox); //target weight, so we'll keep these
      }
    }

    do { //Keep moving items from most overweight box to most underweight box
      $tryRepack = false;
      $this->logger->log(LogLevel::DEBUG,  'boxes under/over target: ' . count($underWeightBoxes) . '/' . count($overWeightBoxes));

      foreach ($underWeightBoxes as $u => $underWeightBox) {
        foreach ($overWeightBoxes as $o => $overWeightBox) {
          $overWeightBoxItems = $overWeightBox->getItems()->asArray();

          //For each item in the heavier box, try and move it to the lighter one
          foreach ($overWeightBoxItems as $oi => $overWeightBoxItem) {
            if ($underWeightBox->getWeight() + $overWeightBoxItem->getWeight() > $targetWeight) {
              continue; //skip if moving this item would hinder rather than help weight distribution
            }

            $newItemsForLighterBox = clone $underWeightBox->getItems();
            $newItemsForLighterBox->insert($overWeightBoxItem);

            $newLighterBoxPacker = new Packer(); //we may need a bigger box
            $newLighterBoxPacker->setBoxes($this->boxes);
            $newLighterBoxPacker->setItems($newItemsForLighterBox);
            $newLighterBox = $newLighterBoxPacker->doVolumePacking()->extract();

            if ($newLighterBox->getItems()->count() === $newItemsForLighterBox->count()) { //new item fits
              unset($overWeightBoxItems[$oi]); //now packed in different box

              $newHeavierBoxPacker = new Packer(); //we may be able to use a smaller box
              $newHeavierBoxPacker->setBoxes($this->boxes);
              $newHeavierBoxPacker->setItems($overWeightBoxItems);

              $overWeightBoxes[$o] = $newHeavierBoxPacker->doVolumePacking()->extract();
              $underWeightBoxes[$u] = $newLighterBox;

              $tryRepack = true; //we did some work, so see if we can do even better
              usort($overWeightBoxes, [$packedBoxes, 'reverseCompare']);
              usort($underWeightBoxes, [$packedBoxes, 'reverseCompare']);
              break 3;
            }
          }
        }
      }
    } while ($tryRepack);

    //Combine back into a single list
    $packedBoxes->insertFromArray($overWeightBoxes);
    $packedBoxes->insertFromArray($underWeightBoxes);

    return $packedBoxes;
  }


  /**
   * Pack as many items as possible into specific given box
   * @param Box      $aBox
   * @param ItemList $aItems
   * @return PackedBox packed box
   */
  public function packIntoBox(Box $aBox, ItemList $aItems) {
    $this->logger->log(LogLevel::DEBUG,  "[EVALUATING BOX] {$aBox->getReference()}");

    $packedItems = new ItemList;
    $remainingDepth = $aBox->getInnerDepth();
    $remainingWeight = null;
    if ($aBox->hasMaxWeight()) {
      $remainingWeight = $aBox->getMaxWeight() - $aBox->getEmptyWeight();
    }
    $remainingWidth = $aBox->getInnerWidth();
    $remainingLength = $aBox->getInnerLength();

    $layerWidth = $layerLength = $layerDepth = 0;
    while(!$aItems->isEmpty()) {

      $itemToPack = $aItems->top();

      if ($itemToPack->getDepth() > $remainingDepth || ( $remainingWeight !== null && $itemToPack->getWeight() > $remainingWeight)) {
        break;
      }

      $this->logger->log(LogLevel::DEBUG,  "evaluating item {$itemToPack->getDescription()}");
      $this->logger->log(LogLevel::DEBUG,  "item dimensions width: {$itemToPack->getWidth()}, length: {$itemToPack->getLength()}, depth: {$itemToPack->getDepth()}");
      $this->logger->log(LogLevel::DEBUG,  "remaining width: {$remainingWidth}, length: {$remainingLength}, depth: {$remainingDepth}");
      $this->logger->log(LogLevel::DEBUG,  "layerWidth: {$layerWidth}, layerLength: {$layerLength}, layerDepth: {$layerDepth}");

      $itemWidth = $itemToPack->getWidth();
      $itemLength = $itemToPack->getLength();
      $itemDepth = $itemToPack->getDepth();

      $isRotateVertical = $itemToPack instanceof RotateItemInterface && $itemToPack->isRotateVertical();

      if (false === $isRotateVertical && $itemToPack->getDepth() > $remainingDepth) {
        break;
      }

      $fitsSameGap = min($remainingWidth - $itemWidth, $remainingLength - $itemLength);
      $fitsRotatedGap = min($remainingWidth - $itemLength, $remainingLength - $itemWidth);

      /*
       * Try vertical rotation when the item can be rotated vertically and it's not a cube.
       * If allowed tip box on side by transferring depth to width,
       */
      if ($isRotateVertical && ($itemWidth !== $itemDepth || $itemLength !== $itemWidth)) {
        $fitsVertical = $remainingDepth - $itemDepth;
        $this->logger->log(LogLevel::DEBUG, "Not a Cube");

        if ($fitsSameGap < 0 || $fitsRotatedGap < 0 || $fitsVertical >= 0) {
          $this->logger->log(LogLevel::DEBUG, "Try vertical rotate item, set depth to length, set width to depth and length to width");

          $itemToPack = $this->rotateItem($itemToPack);
          $itemWidth = $itemToPack->getWidth();
          $itemLength = $itemToPack->getLength();
          $itemDepth = $itemToPack->getDepth();

          $fitsVerticalRotated = $remainingDepth - $itemDepth;
          $fitsSameGap = min($remainingWidth - $itemWidth, $remainingLength - $itemLength);
          $fitsRotatedGap = min($remainingWidth - $itemLength, $remainingLength - $itemWidth);

          $this->logger->log(LogLevel::DEBUG,  "item dimensions width: {$itemToPack->getWidth()}, length: {$itemToPack->getLength()}, depth: {$itemToPack->getDepth()}");

          if ($fitsSameGap < 0 || $fitsRotatedGap < 0 || $fitsVerticalRotated >= 0) {
            $this->logger->log(LogLevel::DEBUG, "Check that rotation is in correct plane");
            $itemToPack = $this->rotateItem($itemToPack);
            $itemWidth = $itemToPack->getWidth();
            $itemLength = $itemToPack->getLength();

            $fitsSameGap = min($remainingWidth - $itemWidth, $remainingLength - $itemLength);
            $fitsRotatedGap = min($remainingWidth - $itemLength, $remainingLength - $itemWidth);
            $this->logger->log(LogLevel::DEBUG,  "item dimensions width: {$itemToPack->getWidth()}, length: {$itemToPack->getLength()}, depth: {$itemToPack->getDepth()}");
          }
        }
      }

      if ($fitsSameGap >= 0 || $fitsRotatedGap >= 0) {
        $packedItems->insert($aItems->extract());
        if ($remainingWeight !== null) {
          $remainingWeight -= $itemToPack->getWeight();
        }

        if ($fitsRotatedGap < 0 ||
            ($fitsSameGap >= 0 && $fitsSameGap <= $fitsRotatedGap) ||
            (!$aItems->isEmpty() && $aItems->top() == $itemToPack && $remainingLength >= 2 * $itemLength)) {
          $this->logger->log(LogLevel::DEBUG,  "fits (better) unrotated");
          $remainingLength -= $itemLength;
          $layerLength += $itemLength;
          $layerWidth = max($itemWidth, $layerWidth);
        }
        else {
          $this->logger->log(LogLevel::DEBUG,  "fits (better) rotated");
          $remainingLength -= $itemWidth;
          $layerLength += $itemWidth;
          $layerWidth = max($itemLength, $layerWidth);
        }
        $layerDepth = max($layerDepth, $itemToPack->getDepth()); //greater than 0, items will always be less deep

        //allow items to be stacked in place within the same footprint up to current layerdepth
        $maxStackDepth = $layerDepth - $itemToPack->getDepth();
        while(!$aItems->isEmpty()) {
          $potentialStackItem = $aItems->top();
          if ($potentialStackItem->getDepth() <= $maxStackDepth &&
              (
                  $remainingWeight !== null &&
                  $potentialStackItem->getWeight() <= $remainingWeight) &&
              $potentialStackItem->getWidth() <= $itemToPack->getWidth() &&
              $potentialStackItem->getLength() <= $itemToPack->getLength()) {

            if ($remainingWeight !== null) {
              $remainingWeight -= $potentialStackItem->getWeight();
            }
            $maxStackDepth -= $potentialStackItem->getDepth();
            $packedItems->insert($aItems->extract());
          }
          else {
            break;
          }
        }
      }
      else {
        if ($remainingWidth >= min($itemWidth, $itemLength) && $layerDepth > 0 && $layerWidth > 0 && $layerLength > 0) {
          $this->logger->log(LogLevel::DEBUG,  "No more fit in lengthwise, resetting for new row");
          $remainingLength += $layerLength;
          $remainingWidth -= $layerWidth;
          $layerWidth = $layerLength = 0;
          continue;
        }

        if ($remainingLength < min($itemWidth, $itemLength) || $layerDepth == 0) {
          $this->logger->log(LogLevel::DEBUG, "doesn't fit on layer even when empty");

          if (false === $this->allowPartialResults) {
            break;
          }

          $this->remainingItems->insert($aItems->extract());
        }
        $remainingWidth = $layerWidth ? min(floor($layerWidth * 1.1), $aBox->getInnerWidth()) : $aBox->getInnerWidth();
        $remainingLength = $layerLength ? min(floor($layerLength * 1.1), $aBox->getInnerLength()) : $aBox->getInnerLength();
        $remainingDepth -= $layerDepth;

        $layerWidth = $layerLength = $layerDepth = 0;
        $this->logger->log(LogLevel::DEBUG,  "doesn't fit, so starting next vertical layer");
      }
    }
    $this->logger->log(LogLevel::DEBUG,  "done with this box");
    return new PackedBox($aBox, $packedItems, $remainingWidth, $remainingLength, $remainingDepth, $remainingWeight);
  }

  /**
   * @param Item $itemToPack
   * @return Item
   */
  public function rotateItem($itemToPack)
  {
    $rotatedItemWidth = $itemToPack->getLength();
    $rotatedItemLength = $itemToPack->getDepth();
    $rotatedItemDepth = $itemToPack->getWidth();

    $itemToPack->setWidth($rotatedItemWidth);
    $itemToPack->setLength($rotatedItemLength);
    $itemToPack->setDepth($rotatedItemDepth);

    return $itemToPack;
  }

  /**
   * @param boolean $allowPartialResults
   */
  public function setAllowPartialResults($allowPartialResults)
  {
    $this->allowPartialResults = $allowPartialResults;
  }

  /**
   * Return any remaining items that did not fit into any boxes
   * @return ItemList
   */
  public function getRemainingItems()
  {
    return $this->remainingItems;
  }

  /**
   * Pack as many items as possible into specific given box
   * @deprecated
   * @param Box      $aBox
   * @param ItemList $aItems
   * @return ItemList items packed into box
   */
  public function packBox(Box $aBox, ItemList $aItems) {
    $packedBox = $this->packIntoBox($aBox, $aItems);
    return $packedBox->getItems();
  }
}
