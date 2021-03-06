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
use FacturaScripts\Plugins\Community\Model\Language;
use FacturaScripts\Plugins\Community\Model\Translation;
use FacturaScripts\Plugins\Community\Model\WebTeamLog;
use FacturaScripts\Plugins\Community\Model\WebTeamMember;
use FacturaScripts\Plugins\webportal\Lib\WebPortal\SectionController;

/**
 * Class to manage an existing translation.
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 * @author Francesc Pineda Segarra <francesc.pineda@x-netdigital.com>
 */
class EditTranslation extends SectionController
{

    /**
     * This translation.
     *
     * @var Translation
     */
    private $translationModel;

    /**
     * Returns true if contact can edit this translation.
     *
     * @return bool
     */
    public function contactCanEdit(): bool
    {
        if ($this->user) {
            return true;
        }

        if (null === $this->contact) {
            return false;
        }

        // Contact is member of translation team?
        $idteamtra = AppSettings::get('community', 'idteamtra');
        $member = new WebTeamMember();
        $where = [
            new DataBaseWhere('idcontacto', $this->contact->idcontacto),
            new DataBaseWhere('idteam', $idteamtra),
            new DataBaseWhere('accepted', true)
        ];
        if (!$member->loadFromCode('', $where)) {
            return false;
        }

        // This language has a mantainer?
        $translation = $this->getTranslationModel();
        $language = new Language();
        $language->loadFromCode($translation->langcode);
        return !($language->idcontacto && $language->idcontacto !== $this->contact->idcontacto);
    }

    /**
     * Returns true if contact can add new translation.
     *
     * @return bool
     */
    public function contactCanAdd(): bool
    {
        if ($this->user) {
            return true;
        }

        if (null === $this->contact) {
            return false;
        }

        // Contact is member of translation team?
        $idteamtra = AppSettings::get('community', 'idteamtra');
        $member = new WebTeamMember();
        $where = [
            new DataBaseWhere('idcontacto', $this->contact->idcontacto),
            new DataBaseWhere('idteam', $idteamtra),
            new DataBaseWhere('accepted', true)
        ];
        if (!$member->loadFromCode('', $where)) {
            return false;
        }

        // This language has a mantainer?
        $translation = $this->getTranslationModel();
        $language = new Language();
        $language->loadFromCode($translation->langcode);
        return !($language->idcontacto && $language->idcontacto !== $this->contact->idcontacto);
    }

    /**
     * Returns the translation loaded by code.
     *
     * @return Translation
     */
    public function getTranslationModel(): Translation
    {
        if (isset($this->translationModel)) {
            return $this->translationModel;
        }

        $translation = new Translation();
        $code = $this->request->get('code', '');
        if (!empty($code)) {
            $translation->loadFromCode($code);
            return $translation;
        }

        $uri = explode('/', $this->uri);
        $translation->loadFromCode(end($uri));
        return $translation;
    }

    /**
     * Check the revisions for this translation.
     *
     * @param Translation $translation
     */
    protected function checkRevisions(Translation $translation)
    {
        $mainLangCode = AppSettings::get('community', 'mainlanguage');
        if ($translation->langcode !== $mainLangCode) {
            return;
        }

        // when we change a translation in main language, we check equivalent translations for revision
        $where = [
            new DataBaseWhere('name', $translation->name),
            new DataBaseWhere('id', $translation->id, '!=')
        ];
        foreach ($translation->all($where, [], 0, 0) as $trans) {
            $trans->needsrevision = true;
            $trans->save();
        }
    }

    /**
     * Load sections to the view.
     */
    protected function createSections()
    {
        $this->addSection('translation', ['fixed' => true, 'template' => 'Section/Translation']);

        $this->addListSection('translations', 'Translation', 'Section/Translations', 'translations', 'fa-copy');
        $this->addSearchOptions('translations', ['name', 'description', 'translation']);
        $this->addOrderOption('translations', 'name', 'code', 1);
        $this->addOrderOption('translations', 'lastmod', 'last-update');

        $this->addListSection('revisions', 'Translation', 'Section/Translations', 'needs-revisions', 'fa-eye');
        $this->addSearchOptions('revisions', ['name', 'description', 'translation']);
        $this->addOrderOption('revisions', 'name', 'code', 1);
        $this->addOrderOption('revisions', 'lastmod', 'last-update');
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

        $translation = $this->getTranslationModel();
        $translation->description = $this->request->request->get('description', '');
        $translation->translation = $this->request->request->get('translation', '');
        $translation->lastmod = date('d-m-Y H:i:s');
        $translation->needsrevision = false;

        $oldTransName = '';
        if ($this->request->request->get('name', '') !== '') {
            $oldTransName = $translation->name;
            $translation->name = $this->request->request->get('name', '');
            $newTransName = $translation->name;
        }

        if ($translation->save()) {
            $this->miniLog->info($this->i18n->trans('record-updated-correctly'));
            $this->checkRevisions($translation);
            $this->updateLanguageStats($translation->langcode);
            $this->saveTeamLog($translation);
            $this->renameTranslation($oldTransName, $newTransName);
        } else {
            $this->miniLog->alert($this->i18n->trans('record-save-error'));
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
            case 'edit':
                $this->editAction();
                return true;

            default:
                return parent::execPreviousAction($action);
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
            case 'revisions':
                $translation = $this->getTranslationModel();
                $where = [
                    new DataBaseWhere('langcode', $translation->langcode),
                    new DataBaseWhere('needsrevision', true),
                    new DataBaseWhere('id', $translation->id, '!=')
                ];
                $this->loadListSection($sectionName, $where);
                break;

            case 'translations':
                $translation = $this->getTranslationModel();
                $where = [
                    new DataBaseWhere('name', $translation->name),
                    new DataBaseWhere('id', $translation->id, '!=')
                ];
                $this->loadListSection($sectionName, $where);
                break;
        }
    }

    /**
     * Store a log detail for the translation.
     *
     * @param Translation $translation
     */
    private function saveTeamLog(Translation $translation)
    {
        $idteamtra = AppSettings::get('community', 'idteamtra', '');
        if (empty($idteamtra)) {
            return;
        }

        $teamLog = new WebTeamLog();
        $teamLog->description = 'Modified translation: ' . $translation->langcode . ' / ' . $translation->name;
        $teamLog->idteam = $idteamtra;
        $teamLog->idcontacto = is_null($this->contact) ? null : $this->contact->idcontacto;
        $teamLog->link = $translation->url('public');
        $teamLog->save();
    }

    /**
     * Updates the details of the language.
     *
     * @param string $langcode
     */
    private function updateLanguageStats(string $langcode)
    {
        $language = new Language();
        if ($language->loadFromCode($langcode)) {
            $language->updateStats();
            $language->save();
        }
    }

    /**
     * Rename all translations to new name.
     */
    private function renameTranslation($oldTransName, $newTransName)
    {
        if ($newTransName !== $oldTransName) {
            // start transaction
            $this->dataBase->beginTransaction();

            // main save process
            try {
                $translation = new Translation();
                $where = [new DataBaseWhere('name', $oldTransName)];
                foreach ($translation->all($where, [], 0, 0) as $pos => $trans) {
                    $trans->name = $newTransName;
                    if (!$trans->save()) {
                        $this->miniLog->alert($this->i18n->trans('record-save-error'));
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
        }
    }
}
