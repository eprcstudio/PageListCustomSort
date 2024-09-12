<?php namespace ProcessWire;

/**
 * Enables the use of a custom sort setting for children, using multiple properties
 * 
 * Copyright (c) 2024 EPRC
 * Licensed under MIT License, see LICENSE
 *
 */

class PageListCustomSort extends WireData implements Module {

	public function init() {
		$this->addHookBefore("Pages::find", $this, "addCustomSortQuery");
		$this->addHookBefore("ProcessPageList::find", $this, "addCustomSortFind");
		$this->addHookAfter("ProcessPageEdit::buildFormChildren", $this, "addCustomOptionPage");
		$this->addHookAfter("ProcessTemplate::buildEditForm", $this, "addCustomOptionTemplate");
		$this->addHookAfter("ProcessPageEdit::processInput", $this, "saveCustomOptionPage");
		$this->addHookBefore("ProcessTemplate::executeSave", $this, "saveCustomOptionTemplate");
	}

	public function addCustomSortFind(HookEvent $event) {
		$selector = $event->arguments(0);
		/** @var Page $page */
		$page = $event->arguments(1);
		$fields = explode(",", $this->getCustomOption($page) ?? "");
		foreach($fields as $field) {
			if(!$field) continue;
			$selector .= ",sort=" . trim($field);
		}
		$event->arguments(0, $selector);
	}

	public function addCustomSortQuery(HookEvent $event) {
		$selector = (string) $event->arguments(0);
		if(strpos($selector, "sort=_custom") === false) return;

		preg_match("/parent_id=(\d+),/", $selector, $matches);
		if(count($matches) <= 1) return;

		$page = $event->pages->get($matches[1]);
		if(!$page->id) return;

		$sort = "";
		$fields = explode(",", $this->getCustomOption($page) ?? "");
		foreach($fields as $field) {
			if(!$field) continue;
			$sort .= ",sort=" . trim($field);
		}
		$selector = preg_replace("/, ?sort=_custom/", $sort, $selector);
		$event->arguments(0, new Selectors($selector));
	}

	public function addCustomOptionPage(HookEvent $event) {
		/** @var Page $page */
		$page = $event->object->getPage();
		/** @var InputfieldWrapper $wrapper */
		$wrapper = $event->return;
		if($fieldset = $wrapper->getChildByName("ChildrenSortSettings")) {
			$this->addCustomOption($fieldset, $this->getCustomOptionPage($page));
		} elseif($fieldset = $wrapper->getChildByName("ChildrenPageList")) {
			if(strpos($fieldset->notes, "_custom") !== false) {
				$fieldset->notes = str_replace("_custom", $page->template->sortfield_custom, $fieldset->notes);
			}
		}
	}

	public function addCustomOptionTemplate(HookEvent $event) {
		/** @var Template $template */
		$template = $event->arguments("template");
		/** @var InputfieldForm $form */
		$form = $event->return;
		/** @var InputfieldFieldset $fieldset */
		$fieldset = $form->getChildByName("sortfield_fieldset");
		$this->addCustomOption($fieldset, $template->sortfield_custom);
	}

	private function addCustomOption(InputfieldFieldset $fieldset, $value = "") {
		/** @var InputfieldSelect $sortfield */
		$sortfield = $fieldset->getChildByName("sortfield");
		$sortfield->insertOptions(["_custom" => $this->_("Custom")], "sort");

		/** @var InputfieldCheckbox $f */
		$sortfieldReverse = $fieldset->getChildByName("sortfield_reverse");
		$sortfieldReverse->showIf = "sortfield!='', sortfield!=sort, sortfield!=_custom";

		/** @var InputfieldText $f */
		$f = $this->modules->get("InputfieldText");
		$f->attr("id+name", "sortfield_custom");
		$f->label = $this->_("Sort string");
		$f->description = $this->_("To sort using multiple fields, type their name separated with a comma. You may also specify subproperties using `property.subproperty`. To reverse the sort of a field, prepend its name with a minus \"-\"");
		$f->placeholder = "-created, name";
		$f->showIf = "sortfield=_custom";
		$f->val($value);
		$fieldset->add($f);
	}

	private function getCustomOption(Page $page) {
		if($page->template->sortfield === "_custom") {
			return $page->template->sortfield_custom;
		} elseif($page->sortfield === "_custom") {
			return $this->getCustomOptionPage($page);
		}
	}

	private function getCustomOptionPage(Page $page) {
		$sortfield_custom = "";
		$sql = "SELECT sortfield_custom " .
			   "FROM pages_sortfields " .
			   "WHERE pages_id=$page->id";
		try {
			$query = $this->wire()->database->prepare($sql);
			$query->execute();
			if($query->rowCount()) {
				$sortfield_custom = $query->fetchColumn();
			}
		} catch(\Exception $e) {
			$this->error($e->getMessage());
		}
		return $sortfield_custom;
	}

	public function saveCustomOptionPage(HookEvent $event) {
		/** @var InputfieldWrapper $form */
		$form = $event->arguments(0);
		if(!$form->getChildByName("ChildrenSortSettings") || $event->arguments("level")) return;

		/** @var Page $page */
		$page = $event->object->getPage();
		$this->addHookAfter("Pages::save(id=$page)", function(HookEvent $event) {
			if($event->return) { // page is successfully saved
				/** @var Page $page */
				$page = $event->arguments("page");

				$sortfield = $this->pages->sortfields()->encode($page->sortfield);
				if($sortfield == 'sort' || !$sortfield) return;

				$sortfield_custom = $event->input->post->selectorValue("sortfield_custom", [
					"useQuotes" => false
				]);

				$sql = "UPDATE pages_sortfields " .
					   "SET sortfield_custom='$sortfield_custom' " .
					   "WHERE pages_id=$page->id";
				try {
					$this->wire()->database->exec($sql);
				} catch(\Exception $e) {
					$this->error($e->getMessage());
				}
			}
		});
	}

	public function saveCustomOptionTemplate(HookEvent $event) {
		/** @var WireInput $input */
		$input = $event->input;
		$id = (int) $input->post("id");
		if(!$id) $id = (int) $input->get("id");
		/** @var Template $template */
		$template = $event->templates->get($id);
		$sortfield_custom = $input->post->selectorValue("sortfield_custom", [
			"useQuotes" => false
		]);
		$template->sortfield_custom = $sortfield_custom;
	}

	public function ___install() {
		$sql = "ALTER TABLE pages_sortfields " .
			   "ADD sortfield_custom text NOT NULL";
		try {
			$this->wire()->database->exec($sql);
		} catch(\Exception $e) {
			$this->error($e->getMessage());
		}
	}

	public function ___uninstall() {
		$sql = "ALTER TABLE pages_sortfields " .
			   "DROP COLUMN sortfield_custom";
		try {
			$this->wire()->database->exec($sql);
		} catch(\Exception $e) {
			$this->error($e->getMessage());
		}
	}
}