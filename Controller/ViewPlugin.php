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
use FacturaScripts\Plugins\Community\Model\WebProject;
use FacturaScripts\Plugins\Community\Model\WebTeamMember;
use FacturaScripts\Plugins\webportal\Lib\WebPortal\SectionController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Description of ViewPlugin
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class ViewPlugin extends SectionController
{

    /**
     * The project related to this plugin.
     *
     * @var WebProject
     */
    protected $project;

    /**
     * * Returns true if contact can edit this plugin.
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

        $idteamdev = AppSettings::get('community', 'idteamdev', '');
        if (empty($idteamdev)) {
            return false;
        }

        $member = new WebTeamMember();
        $where = [
            new DataBaseWhere('idcontacto', $this->contact->idcontacto),
            new DataBaseWhere('idteam', $idteamdev),
            new DataBaseWhere('accepted', true)
        ];

        return $member->loadFromCode('', $where);
    }

    /**
     * Return the project by code.
     *
     * @return WebProject
     */
    public function getProject(): WebProject
    {
        if (isset($this->project)) {
            return $this->project;
        }

        $project = new WebProject();
        $code = $this->request->get('code', '');
        if (!empty($code)) {
            $project->loadFromCode($code);
            return $project;
        }

        $uri = explode('/', $this->uri);
        $project->loadFromCode('', [new DataBaseWhere('name', end($uri))]);
        return $project;
    }

    /**
     * Load sections to the view.
     */
    protected function createSections()
    {
        $this->addSection('plugin', ['fixed' => true, 'template' => 'Section/Plugin']);
        $this->addListSection('docs', 'WebDocPage', 'Section/Documentation', 'documentation');
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

        $this->project->description = $this->request->get('description', '');
        $this->project->publicrepo = $this->request->get('publicrepo', '');
        if ($this->project->save()) {
            $this->miniLog->info($this->i18n->trans('record-updated-correctly'));
        } else {
            $this->miniLog->alert($this->i18n->trans('record-save-error'));
        }
    }

    /**
     * Runs the controller actions after data read.
     *
     * @param string $action
     */
    protected function execAfterAction(string $action)
    {
        switch ($action) {
            case 'edit':
                $this->editAction();
                break;
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
            case 'docs':
                $project = $this->getProject();
                $where = [
                    new DataBaseWhere('idproject', $project->idproject),
                    new DataBaseWhere('idparent', null, 'IS'),
                ];
                $this->loadListSection($sectionName, $where);
                break;

            case 'plugin':
                $this->loadPlugin();
                break;
        }
    }

    /**
     * Load the plugin data.
     */
    protected function loadPlugin()
    {
        $this->project = $this->getProject();
        if ($this->project->exists() && $this->project->plugin) {
            $this->title = 'Plugin ' . $this->project->name;
            $this->description = $this->project->description();
            return;
        }

        $this->miniLog->alert($this->i18n->trans('no-data'));
        $this->response->setStatusCode(Response::HTTP_NOT_FOUND);
        $this->webPage->noindex = true;
        $this->setTemplate('Master/Portal404');
    }
}
