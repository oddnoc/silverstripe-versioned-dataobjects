<?php

class VersionedDataObjects extends DataExtension {

	public function canPublish($member = null) {
		if (!$member || !(is_a($member, 'Member')) || is_numeric($member)) {
			$member = Member::currentUser();
		}
		if ($member && Permission::checkMember($member, "ADMIN")) {
			return true;
		}
		return $this->owner->canEdit($member);
	}

	public function canDeleteFromLive($member = null) {
		return $this->canPublish($member);
	}

	/**
	 * Check if this page is new - that is, if it has yet to have been written
	 * to the database.
	 *
	 * @return boolean True if this page is new.
	 */
	public function isNew() {
		if (empty($this->owner->ID)) {
			return true;
		}

		if (is_numeric($this->owner->ID)) {
			return false;
		}

		return stripos($this->owner->ID, 'new') === 0;
	}

	/**
	 * Check if this page has been published.
	 *
	 * @return boolean True if this page has been published.
	 */
	public function isPublished() {
		if ($this->isNew()) {
			return false;
		}
		
		return (DB::query("SELECT \"ID\" FROM \"{$this->owner->ClassName}_Live\" WHERE \"ID\" = {$this->owner->ID}")->value()) ? true : false;
	}

	
	/**
	 * Compares current draft with live version,
	 * and returns TRUE if no draft version of this page exists,
	 * but the page is still published (after triggering "Delete from draft site" in the CMS).
	 * 
	 * @return boolean
	 */
	public function getIsDeletedFromStage() {
		if(!$this->owner->ID) return true;
		if($this->isNew()) return false;
		
		$stageVersion = Versioned::get_versionnumber_by_stage($this->owner->ClassName, 'Stage', $this->owner->ID);

		// Return true for both completely deleted pages and for pages just deleted from stage.
		return !($stageVersion);
	}
	
	/**
	 * Return true if this page exists on the live site
	 */
	public function getExistsOnLive() {
		return (bool)Versioned::get_versionnumber_by_stage($this->owner->ClassName, 'Live', $this->owner->ID);
	}

	/**
	 * Compares current draft with live version,
	 * and returns TRUE if these versions differ,
	 * meaning there have been unpublished changes to the draft site.
	 * 
	 * @return boolean
	 */
	public function getIsModifiedOnStage() {
		// new unsaved pages could be never be published
		if($this->isNew()) return false;
		
		$stageVersion = Versioned::get_versionnumber_by_stage($this->owner->ClassName, 'Stage', $this->owner->ID);
		$liveVersion =	Versioned::get_versionnumber_by_stage($this->owner->ClassName, 'Live', $this->owner->ID);

		return ($stageVersion && $stageVersion != $liveVersion);
	}
	
	/**
	 * Compares current draft with live version,
	 * and returns true if no live version exists,
	 * meaning the page was never published.
	 * 
	 * @return boolean
	 */
	public function getIsAddedToStage() {
		// new unsaved pages could be never be published
		if($this->isNew()) return false;
		
		$stageVersion = Versioned::get_versionnumber_by_stage($this->owner->ClassName, 'Stage', $this->owner->ID);
		$liveVersion =	Versioned::get_versionnumber_by_stage($this->owner->ClassName, 'Live', $this->owner->ID);

		return ($stageVersion && !$liveVersion);
	}

}

/**
 * Adds UI fields to the CMS for DataObjects that have Versioned
 * applied to them and are not SiteTree-related.
 */
class VersionedDataObjects_GridFieldDetailForm_ItemRequestExtension extends Extension {

	public function updateItemEditForm(Form $form) {
		$record = $form->getRecord();
		if ($record->hasExtension('Versioned')) {
			$actions = $form->Actions();
			$this->addVersionedActions($actions, $record);
		}
	}

	/**
	 * Add versioning actions. Adapted from SiteTree#getCMSActions
	 * 
	 * @param FieldList $actions
	 */
	protected function addVersionedActions(FieldList $actions, DataObject $record) {

		$minorActions = CompositeField::create()->setTag('fieldset')->addExtraClass('ss-ui-buttonset');
		$actions->push($minorActions);

		if ($record->isPublished() && $record->canPublish() && !$record->IsDeletedFromStage && $record->canDeleteFromLive()) {
			// "unpublish"
			$minorActions->push(
					FormAction::create('unpublish', _t('SiteTree.BUTTONUNPUBLISH', 'Unpublish'), 'delete')
							->setDescription(_t('SiteTree.BUTTONUNPUBLISHDESC', 'Remove this page from the published site'))
							->addExtraClass('ss-ui-action-destructive')->setAttribute('data-icon', 'unpublish')
			);
		}

		if ($record->stagesDiffer('Stage', 'Live') && !$record->IsDeletedFromStage) {
			if ($record->isPublished() && $record->canEdit()) {
				// "rollback"
				$minorActions->push(
						FormAction::create('rollback', _t('SiteTree.BUTTONCANCELDRAFT', 'Cancel draft changes'), 'delete')
								->setDescription(_t('SiteTree.BUTTONCANCELDRAFTDESC',
												'Delete your draft and revert to the currently published page'))
				);
			}
		}

		if ($record->canEdit()) {
			if ($record->IsDeletedFromStage) {
				if ($record->ExistsOnLive) {
					// "restore"
					$minorActions->push(FormAction::create('revert', _t('CMSMain.RESTORE', 'Restore')));
					if ($record->canDelete() && $record->canDeleteFromLive()) {
						// "delete from live"
						$minorActions->push(
								FormAction::create('deletefromlive', _t('CMSMain.DELETEFP', 'Delete'))->addExtraClass('ss-ui-action-destructive')
						);
					}
				}
				else {
					// "restore"
					$minorActions->push(
							FormAction::create('restore', _t('CMSMain.RESTORE', 'Restore'))->setAttribute('data-icon', 'decline')
					);
				}
			}
			else {
				if ($record->canDelete()) {
					// "delete"
					$minorActions->push(
							FormAction::create('delete', _t('CMSMain.DELETE', 'Delete draft'))->addExtraClass('delete ss-ui-action-destructive')
									->setAttribute('data-icon', 'decline')
					);
				}

				// "save"
				$minorActions->push(
						FormAction::create('save', _t('CMSMain.SAVEDRAFT', 'Save Draft'))->setAttribute('data-icon', 'addpage')
				);
			}
		}

		if ($record->canPublish() && !$record->IsDeletedFromStage) {
			// "publish"
			$actions->push(
					FormAction::create('publish', _t('SiteTree.BUTTONSAVEPUBLISH', 'Save & Publish'))
							->addExtraClass('ss-ui-action-constructive')->setAttribute('data-icon', 'accept')
			);
		}
	}

}

class VersionedDataObjects_ModelAdmin extends Extension {
	
}
