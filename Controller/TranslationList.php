<?php
/**
 * This file is part of Community plugin for FacturaScripts.
 * Copyright (C) 2018 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\Plugins\Community\Controller;

use FacturaScripts\Plugins\Community\Model\Language;
use FacturaScripts\Plugins\webportal\Lib\WebPortal\SectionController;

/**
 * Description of TranslationList
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class TranslationList extends SectionController
{

    /**
     * Load sections to the view.
     */
    protected function createSections()
    {
        $this->addListSection('languages', 'Language', 'Section/Languages', 'languages', 'fa-language');
        $this->addSearchOptions('languages', ['langcode', 'description']);
        $this->addOrderOption('languages', 'langcode', 'code');
        $this->addOrderOption('languages', 'description', 'description');
        $this->addOrderOption('languages', 'lastmod', 'last-update');
        $this->addOrderOption('languages', 'numtranslations', 'number-of-translations', 2);

        if ($this->user) {
            $this->addButton('languages', $this->url() . '?action=import-lang', 'import', '');
        }

        $this->addListSection('translations', 'Translation', 'Section/Translations', 'translations', 'fa-copy');
        $this->addSearchOptions('translations', ['name', 'description', 'translation']);
        $this->addOrderOption('translations', 'name', 'code', 1);
        $this->addOrderOption('translations', 'lastmod', 'last-update');
    }

    /**
     * Run the actions that alter data before reading it.
     *
     * @param string $action
     *
     * @return bool
     */
    protected function execPreviousAction(string $action)
    {
        switch ($action) {
            case 'import-lang':
                $this->importLanguagesAction();
                return true;

            default:
                return parent::execPreviousAction($action);
        }
    }

    /**
     * Code for import languages action.
     */
    protected function importLanguagesAction()
    {
        if (!$this->user) {
            $this->miniLog->alert($this->i18n->trans('not-allowed-modify'));
            return;
        }

        foreach ($this->i18n->getAvailableLanguages() as $key => $value) {
            $language = new Language();
            $language->langcode = $key;
            $language->description = $value;
            $language->save();
        }
    }

    /**
     * Load section data procedure
     *
     * @param string $sectionName
     */
    protected function loadData(string $sectionName)
    {
        switch ($sectionName) {
            case 'languages':
                $this->loadListSection($sectionName);
                break;

            case 'translations':
                $this->loadListSection($sectionName);
                break;
        }
    }
}
