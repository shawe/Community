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

use FacturaScripts\Core\App\AppSettings;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\Utils;
use FacturaScripts\Plugins\Community\Model\Language;
use FacturaScripts\Plugins\Community\Model\Translation;
use FacturaScripts\Plugins\Community\Model\WebProject;
use FacturaScripts\Plugins\Community\Model\WebTeamMember;
use FacturaScripts\Plugins\webportal\Lib\WebPortal\SectionController;

/**
 * Class to manage an existing language.
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 * @author Francesc Pineda Segarra <francesc.pineda@x-netdigital.com>
 */
class EditLanguage extends SectionController
{

    /**
     * This language.
     *
     * @var Language
     */
    private $languageModel;

    /**
     * A list of main translations.
     *
     * @var array
     */
    private $mainTranslations = [];

    /**
     * A list of available projects.
     *
     * @var WebProject
     */
    public $projects;

    /**
     * Returns true if contact can edit this language.
     *
     * @return bool
     */
    public function contactCanEdit(): bool
    {
        if ($this->user) {
            return true;
        }

        return false;
    }

    /**
     * Returns true if contact can add this language.
     *
     * @return bool
     */
    public function contactCanAdd(): bool
    {
        if ($this->user) {
            return true;
        }

        return false;
    }

    /**
     * Returns the language loaded by code.
     *
     * @return Language
     */
    public function getLanguageModel(): Language
    {
        if (isset($this->languageModel)) {
            return $this->languageModel;
        }

        $language = new Language();
        $code = $this->request->get('code', '');
        if (!empty($code)) {
            $language->loadFromCode($code);
            return $language;
        }

        $uri = explode('/', $this->uri);
        $language->loadFromCode(end($uri));
        return $language;
    }

    /**
     * Get a list of the parent languages.
     *
     * @return array
     */
    public function getParentLanguages(): array
    {
        $current = $this->getLanguageModel();
        $languages = [];
        foreach ($current->all([], ['langcode' => 'ASC'], 0, 0) as $language) {
            if ($language->langcode == $current->langcode) {
                continue;
            }

            if ($language->parentcode) {
                continue;
            }

            $languages[] = $language;
        }

        return $languages;
    }

    /**
     * Returns a list of team members for this language.
     *
     * @return array
     */
    public function getTeamMembers(): array
    {
        $idteamtra = AppSettings::get('community', 'idteamtra');
        $memberModel = new WebTeamMember();
        $where = [
            new DataBaseWhere('idteam', $idteamtra),
            new DataBaseWhere('accepted', true),
        ];

        $values = [];
        foreach ($memberModel->all($where, [], 0, 0) as $member) {
            $values[] = $member->getContact();
        }

        return $values;
    }

    /**
     * Check available translations with translation name.
     *
     * @param Language $language
     * @param string   $translationName
     *
     * @return bool
     */
    private function checkTranslation(&$language, $translationName): bool
    {
        $mainLangCode = AppSettings::get('community', 'mainlanguage');
        if ($language->langcode === $mainLangCode) {
            return true;
        }

        if (empty($this->mainTranslations)) {
            $this->mainTranslations = [];
            $translation = new Translation();
            $where = [new DataBaseWhere('langcode', $mainLangCode)];
            foreach ($translation->all($where, [], 0, 0) as $trans) {
                $this->mainTranslations[] = $trans->name;
            }
        }

        return in_array($translationName, $this->mainTranslations);
    }

    /**
     * Load sections to the view.
     */
    protected function createSections()
    {
        $this->addSection('language', ['fixed' => true, 'template' => 'Section/Language']);

        $this->addListSection('translations', 'Translation', 'Section/Translations', 'translations', 'fa-copy');
        $this->addSearchOptions('translations', ['name', 'description', 'translation']);
        $this->addOrderOption('translations', 'name', 'code', 1);
        $this->addOrderOption('translations', 'lastmod', 'last-update');

        if ($this->user) {
            $language = $this->getLanguageModel();
            $this->addButton('translations', $language->url() . '&action=import-trans', 'import', '');
        }

        $this->addListSection('revisions', 'Translation', 'Section/Translations', 'needs-revisions', 'fa-eye');
        $this->addSearchOptions('revisions', ['name', 'description', 'translation']);
        $this->addOrderOption('revisions', 'name', 'code', 1);
        $this->addOrderOption('revisions', 'lastmod', 'last-update');
    }

    /**
     * Code for delete action.
     */
    protected function deleteAction()
    {
        if (!$this->contactCanEdit()) {
            $this->miniLog->alert($this->i18n->trans('not-allowed-delete'));
            return;
        }

        $language = $this->getLanguageModel();
        if ($language->delete()) {
            $this->miniLog->info($this->i18n->trans('record-deleted-correctly'));
        }
    }

    /**
     * Code for edit action.
     */
    protected function editAction()
    {
        if (!$this->contactCanEdit()) {
            $this->miniLog->alert($this->i18n->trans('not-allowed-modify'));
            return;
        }

        $language = $this->getLanguageModel();
        $language->description = $this->request->request->get('description', '');
        $language->idcontacto = ('' === $this->request->request->get('idcontacto', '')) ? null : $this->request->request->get('idcontacto', '');
        $language->parentcode = ('' === $this->request->request->get('parentcode', '')) ? null : $this->request->request->get('parentcode', '');

        if ($language->save()) {
            $this->miniLog->info($this->i18n->trans('record-updated-correctly'));
        } else {
            $this->miniLog->alert($this->i18n->trans('record-save-error'));
        }
    }

    /**
     * Code for action to add a new translation.
     */
    protected function addTranslationAction()
    {
        if (!$this->contactCanAdd()) {
            $this->miniLog->alert($this->i18n->trans('not-allowed-add'));
            return;
        }

        // start transaction
        $this->dataBase->beginTransaction();

        // main save process
        try {
            $languages = new Language();
            foreach ($languages->all([], [], 0, 0) as $lang) {
                $trans = new Translation();
                $name = $this->request->request->get('name');
                if (!$trans->loadFromCode($name)) {
                    $trans->langcode = $lang->langcode;
                    $trans->name = $name;
                    $trans->translation = $this->request->request->get('translation');
                    $trans->description = $this->request->request->get('description', '');
                    $trans->idproject = $this->request->request->get('idproject');

                    if (!$trans->save()) {
                        $this->miniLog->alert($this->i18n->trans('record-save-error'));
                    }
                } else {
                    $this->miniLog->alert($this->i18n->trans('record-yet-exists'));
                }
            }
            // confirm data
            $this->dataBase->commit();
            $this->miniLog->info($this->i18n->trans('data-saved'));
        } catch (\Exception $e) {
            $this->miniLog->alert($e->getMessage());
        } finally {
            if ($this->dataBase->inTransaction()) {
                $this->dataBase->rollback();
            }
        }
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
            case 'add':
                $this->addTranslationAction();
                return true;

            case 'delete':
                $this->deleteAction();
                return true;

            case 'edit':
                $this->editAction();
                return true;

            case 'import-trans':
                $this->importTranslationsAction();
                return true;

            case 'json':
                $this->jsonExport();
                return false;

            default:
                return parent::execPreviousAction($action);
        }
    }

    /**
     * Import all translations from Core.
     */
    protected function importTranslationsAction()
    {
        if (!$this->user) {
            $this->miniLog->alert($this->i18n->trans('not-allowed-modify'));
            return;
        }

        $language = $this->getLanguageModel();
        if ($language->parentcode) {
            $this->miniLog->alert("You can't import a language with parent.");
            return;
        }

        // import translations from file
        $newTranslations = [];
        $idproject = AppSettings::get('community', 'idproject');
        $json = (array) json_decode(file_get_contents(FS_FOLDER . '/Core/Translation/' . $language->langcode . '.json'), true);

        // start transaction
        $this->dataBase->beginTransaction();

        // main save process
        try {
            foreach ($json as $key => $value) {
                $translation = new Translation();
                $translation->idproject = $idproject;
                $translation->langcode = $language->langcode;
                $translation->name = $key;
                $translation->description = $translation->translation = $value;
                $translation->needsrevision = false;

                /// is this string in the main language?
                if (!$this->checkTranslation($language, $key)) {
                    continue;
                }

                if ($translation->save()) {
                    $newTranslations[] = $key;
                }
            }
            // confirm data
            $this->dataBase->commit();
        } catch (\Exception $e) {
            $this->miniLog->alert($e->getMessage());
        } finally {
            if ($this->dataBase->inTransaction()) {
                $this->dataBase->rollback();
            }
        }

        // generate missing translations
        $mainLangCode = AppSettings::get('community', 'mainlanguage');
        foreach ($this->mainTranslations as $mainKey) {
            if (in_array($mainKey, $newTranslations)) {
                continue;
            }

            // we need main translation
            $mainTranslation = new Translation();
            $where = [
                new DataBaseWhere('langcode', $mainLangCode),
                new DataBaseWhere('name', $mainKey)
            ];
            $mainTranslation->loadFromCode('', $where);

            $newTranslation = new Translation();
            $newTranslation->description = $mainTranslation->description;
            $newTranslation->idproject = $idproject;
            $newTranslation->langcode = $language->langcode;
            $newTranslation->lastmod = $mainTranslation->lastmod;
            $newTranslation->name = $mainTranslation->name;
            $newTranslation->translation = $mainTranslation->translation;
            $newTranslation->save();
        }

        $language->updateStats();
        $language->save();
    }

    /**
     * Export content to a JSON file.
     */
    protected function jsonExport()
    {
        $this->setTemplate(false);

        $json = [];
        $language = $this->getLanguageModel();
        $translation = new Translation();
        $where = [new DataBaseWhere('langcode', $language->langcode)];
        foreach ($translation->all($where, ['name' => 'ASC'], 0, 0) as $trans) {
            $json[$trans->name] = Utils::fixHtml($trans->translation);
        }

        $this->response->headers->set('Content-Type', 'application/json');
        $this->response->setContent(json_encode($json));
    }

    /**
     * Load section data procedure
     *
     * @param string $sectionName
     */
    protected function loadData(string $sectionName)
    {
        $this->projects = (new WebProject())->all([], [], 0, 0);

        switch ($sectionName) {
            case 'revisions':
                $language = $this->getLanguageModel();
                $where = [
                    new DataBaseWhere('langcode', $language->langcode),
                    new DataBaseWhere('needsrevision', true)
                ];
                $this->loadListSection($sectionName, $where);
                break;

            case 'translations':
                $language = $this->getLanguageModel();
                $where = [new DataBaseWhere('langcode', $language->langcode)];
                $this->loadListSection($sectionName, $where);
                break;
        }
    }
}
