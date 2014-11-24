<?php
/*
Copyright (C) 2014, Siemens AG

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

namespace Fossology\Lib\BusinessRules;

use Fossology\Lib\Data\ClearingDecision;
use Fossology\Lib\Data\DecisionScopes;
use IllegalArguentException;
use Mockery as M;

class ClearingDecisionFilterTest extends \PHPUnit_Framework_TestCase {

  /** @var ClearingDecisionFilter */
  private $clearingDecisionFilter;

  public function setUp() {
    $this->clearingDecisionFilter = new ClearingDecisionFilter();
  }

  public function tearDown() {
    M::close();
  }

  public function testFilterRelevantClearingDecisionsFiltersItemScopedDecisionsNotFromSameFolder() {
    $irrelevantDecision = M::mock(ClearingDecision::classname());
    $irrelevantDecision->shouldReceive("getScope")->once()->withNoArgs()->andReturn(DecisionScopes::ITEM);
    $irrelevantDecision->shouldReceive("getSameFolder")->once()->withNoArgs()->andReturn(false);

    $filteredClearingDecisions = $this->clearingDecisionFilter->filterRelevantClearingDecisions(array($irrelevantDecision));

    assertThat($filteredClearingDecisions, is(emptyArray()));
  }

  public function testFilterRelevantClearingDecisionsFiltersItemScopedDecisionsFromSameFolder() {
    $relevantDecision = M::mock(ClearingDecision::classname());
    $relevantDecision->shouldReceive("getScope")->once()->withNoArgs()->andReturn(DecisionScopes::ITEM);
    $relevantDecision->shouldReceive("getSameFolder")->once()->withNoArgs()->andReturn(true);

    $filteredClearingDecisions = $this->clearingDecisionFilter->filterRelevantClearingDecisions(array($relevantDecision));

    assertThat($filteredClearingDecisions, containsInAnyOrder($relevantDecision));
  }

  public function testFilterRelevantClearingDecisionsFiltersRepoScopedDecisions() {
    $relevantDecision = M::mock(ClearingDecision::classname());
    $relevantDecision->shouldReceive("getScope")->once()->withNoArgs()->andReturn(DecisionScopes::REPO);

    $filteredClearingDecisions = $this->clearingDecisionFilter->filterRelevantClearingDecisions(array($relevantDecision));

    assertThat($filteredClearingDecisions, containsInAnyOrder($relevantDecision));
  }

  public function testFilterCurrentClearingDecisionsShouldKeepNewerRepoScopedDecisions() {
    $itemId = 543;
    $decision1 = M::mock(ClearingDecision::classname());
    $decision1->shouldReceive("getScope")->atLeast()->once()->withNoArgs()->andReturn(DecisionScopes::REPO);
    $decision1->shouldReceive("getUploadTreeId")->andReturn($itemId);
    $decision2 = M::mock(ClearingDecision::classname());
    $decision2->shouldReceive("getScope")->atLeast()->once()->withNoArgs()->andReturn(DecisionScopes::REPO);
    $decision2->shouldReceive("getUploadTreeId")->andReturn($itemId);

    $filteredClearingDecisions = $this->clearingDecisionFilter->filterCurrentClearingDecisions(array($decision1, $decision2));

    assertThat($filteredClearingDecisions, containsInAnyOrder($decision1));
  }

  public function testFilterCurrentClearingDecisionsShouldKeepNewerItemScopedDecisions() {
    $itemId = 543;
    $decision1 = M::mock(ClearingDecision::classname());
    $decision1->shouldReceive("getScope")->atLeast()->once()->withNoArgs()->andReturn(DecisionScopes::ITEM);
    $decision1->shouldReceive("getSameFolder")->once()->withNoArgs()->andReturn(true);
    $decision1->shouldReceive("getUploadTreeId")->andReturn($itemId);
    $decision2 = M::mock(ClearingDecision::classname());
    $decision2->shouldReceive("getScope")->atLeast()->once()->withNoArgs()->andReturn(DecisionScopes::ITEM);
    $decision2->shouldReceive("getSameFolder")->once()->withNoArgs()->andReturn(true);
    $decision2->shouldReceive("getUploadTreeId")->andReturn($itemId);

    $filteredClearingDecisions = $this->clearingDecisionFilter->filterCurrentClearingDecisions(array($decision1, $decision2));

    assertThat($filteredClearingDecisions, containsInAnyOrder($decision1));
  }

  public function testFilterCurrentClearingDecisionsShouldPrioritizeOlderItemScopedDecisionsOverRepoScopedOnes() {
    $itemId = 543;
    $decision1 = M::mock(ClearingDecision::classname());
    $decision1->shouldReceive("getScope")->atLeast()->once()->withNoArgs()->andReturn(DecisionScopes::REPO);
    $decision1->shouldReceive("getUploadTreeId")->andReturn($itemId);
    $decision2 = M::mock(ClearingDecision::classname());
    $decision2->shouldReceive("getScope")->atLeast()->once()->withNoArgs()->andReturn(DecisionScopes::ITEM);
    $decision2->shouldReceive("getSameFolder")->once()->withNoArgs()->andReturn(true);
    $decision2->shouldReceive("getUploadTreeId")->andReturn($itemId);

    $filteredClearingDecisions = $this->clearingDecisionFilter->filterCurrentClearingDecisions(array($decision1, $decision2));

    assertThat($filteredClearingDecisions, containsInAnyOrder($decision2));
  }

  public function testFilterCurrentClearingDecisionsShouldNotPrioritizeOlderRepoScopedDecisionsOverItemScopedOnes() {
    $itemId = 543;
    $decision1 = M::mock(ClearingDecision::classname());
    $decision1->shouldReceive("getScope")->atLeast()->once()->withNoArgs()->andReturn(DecisionScopes::ITEM);
    $decision1->shouldReceive("getSameFolder")->once()->withNoArgs()->andReturn(true);
    $decision1->shouldReceive("getUploadTreeId")->andReturn($itemId);
    $decision2 = M::mock(ClearingDecision::classname());
    $decision2->shouldReceive("getScope")->atLeast()->once()->withNoArgs()->andReturn(DecisionScopes::REPO);
    $decision2->shouldReceive("getUploadTreeId")->andReturn($itemId);

    $filteredClearingDecisions = $this->clearingDecisionFilter->filterCurrentClearingDecisions(array($decision1, $decision2));

    assertThat($filteredClearingDecisions, containsInAnyOrder($decision1));
  }

  /**
   * @expectedException \InvalidArgumentException
   * @expectedExceptionMessage unhandled clearing decision scope '12345'
   */
  public function testCreateClearingResultCreationFailsOfNoEventsWereFound()
  {
    $itemId = 543;
    $decision = M::mock(ClearingDecision::classname());
    $decision->shouldReceive("getScope")->atLeast()->once()->withNoArgs()->andReturn(12345);
    $decision->shouldReceive("getUploadTreeId")->andReturn($itemId);

    $this->clearingDecisionFilter->filterCurrentClearingDecisions(array($decision));
  }

  public function testFilterCurrentReusableClearingDecisionsShouldKeepNewerRepoScopedDecisions() {
    $itemId = 543;
    $decision1 = M::mock(ClearingDecision::classname());
    $decision1->shouldReceive("getScope")->atLeast()->once()->withNoArgs()->andReturn(DecisionScopes::REPO);
    $decision1->shouldReceive("getUploadTreeId")->andReturn($itemId);
    $decision2 = M::mock(ClearingDecision::classname());
    $decision2->shouldReceive("getScope")->atLeast()->once()->withNoArgs()->andReturn(DecisionScopes::REPO);
    $decision2->shouldReceive("getUploadTreeId")->andReturn($itemId);

    $filteredClearingDecisions = $this->clearingDecisionFilter->filterCurrentReusableClearingDecisions(array($decision1, $decision2));

    assertThat($filteredClearingDecisions, containsInAnyOrder($decision1));
  }

  public function testFilterCurrentReusableClearingDecisionsShouldNotKeepNewerItemScopedDecisions() {
    $itemId = 543;
    $decision1 = M::mock(ClearingDecision::classname());
    $decision1->shouldReceive("getScope")->atLeast()->once()->withNoArgs()->andReturn(DecisionScopes::ITEM);
    $decision1->shouldReceive("getSameFolder")->never()->withNoArgs();
    $decision1->shouldReceive("getUploadTreeId")->andReturn($itemId);
    $decision2 = M::mock(ClearingDecision::classname());
    $decision2->shouldReceive("getScope")->atLeast()->once()->withNoArgs()->andReturn(DecisionScopes::ITEM);
    $decision2->shouldReceive("getSameFolder")->never()->withNoArgs();
    $decision2->shouldReceive("getUploadTreeId")->andReturn($itemId);

    $filteredClearingDecisions = $this->clearingDecisionFilter->filterCurrentReusableClearingDecisions(array($decision1, $decision2));

    assertThat($filteredClearingDecisions, is(emptyArray()));
  }

  public function testFilterCurrentReusableClearingDecisionsShouldPrioritizeOlderItemScopedDecisionsOverRepoScopedOnes() {
    $itemId = 543;
    $decision1 = M::mock(ClearingDecision::classname());
    $decision1->shouldReceive("getScope")->atLeast()->once()->withNoArgs()->andReturn(DecisionScopes::REPO);
    $decision1->shouldReceive("getUploadTreeId")->andReturn($itemId);
    $decision2 = M::mock(ClearingDecision::classname());
    $decision2->shouldReceive("getScope")->atLeast()->once()->withNoArgs()->andReturn(DecisionScopes::ITEM);
    $decision2->shouldReceive("getSameFolder")->never()->withNoArgs();
    $decision2->shouldReceive("getUploadTreeId")->andReturn($itemId);

    $filteredClearingDecisions = $this->clearingDecisionFilter->filterCurrentReusableClearingDecisions(array($decision1, $decision2));

    assertThat($filteredClearingDecisions, containsInAnyOrder($decision1));
  }

  public function testFilterCurrentReusableClearingDecisionsShouldNotPrioritizeOlderRepoScopedDecisionsOverItemScopedOnes() {
    $itemId = 543;
    $decision1 = M::mock(ClearingDecision::classname());
    $decision1->shouldReceive("getScope")->atLeast()->once()->withNoArgs()->andReturn(DecisionScopes::ITEM);
    $decision1->shouldReceive("getSameFolder")->never()->withNoArgs();
    $decision1->shouldReceive("getUploadTreeId")->andReturn($itemId);
    $decision2 = M::mock(ClearingDecision::classname());
    $decision2->shouldReceive("getScope")->atLeast()->once()->withNoArgs()->andReturn(DecisionScopes::REPO);
    $decision2->shouldReceive("getUploadTreeId")->andReturn($itemId);

    $filteredClearingDecisions = $this->clearingDecisionFilter->filterCurrentReusableClearingDecisions(array($decision1, $decision2));

    assertThat($filteredClearingDecisions, containsInAnyOrder($decision2));
  }
}
 