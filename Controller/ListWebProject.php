<?php
/**
 * This file is part of Community plugin for FacturaScripts.
 * Copyright (C) 2018 Carlos Garcia Gomez  <carlos@facturascripts.com>
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

use FacturaScripts\Dinamic\Lib\ExtendedController;

/**
 * Description of ListWebProject controller.
 *
 * @author Carlos García Gómez
 */
class ListWebProject extends ExtendedController\ListController
{

    public function getPageData()
    {
        $pageData = parent::getPageData();
        $pageData['title'] = 'projects';
        $pageData['menu'] = 'web';
        $pageData['icon'] = 'fa-folder';

        return $pageData;
    }

    protected function createViews()
    {
        /// projects
        $this->addView('ListWebProject', 'WebProject', 'projects', 'fa-folder');
        $this->addSearchFields('ListWebProject', ['name']);
        $this->addOrderBy('ListWebProject', 'name');
        $this->addOrderBy('ListWebProject', 'creationdate', 'date');

        /// doc pages
        $this->addView('ListWebDocPage', 'WebDocPage', 'documentation', 'fa-book');
        $this->addSearchFields('ListWebDocPage', ['title', 'body']);
        $this->addOrderBy('ListWebDocPage', 'title');
        $this->addOrderBy('ListWebDocPage', 'creationdate', 'date');
        $this->addOrderBy('ListWebDocPage', 'lastmod', 'last-update', 2);
        $this->addOrderBy('ListWebDocPage', 'visitcount');

        $projects = $this->codeModel->all('webprojects', 'idproject', 'name');
        $this->addFilterSelect('ListWebDocPage', 'idproject', 'project', 'idproject', $projects);

        $langValues = $this->codeModel->all('webdocpages', 'langcode', 'langcode');
        $this->addFilterSelect('ListWebDocPage', 'langcode', 'language', 'langcode', $langValues);

        /// builds
        $this->addView('ListWebBuild', 'WebBuild', 'builds', 'fa-file-archive-o');
        $this->addSearchFields('ListWebBuild', ['path']);
        $this->addOrderBy('ListWebBuild', 'version');
        $this->addOrderBy('ListWebBuild', 'date');
        
        $this->addFilterCheckbox('ListWebBuild', 'beta', 'beta', 'beta');
        $this->addFilterCheckbox('ListWebBuild', 'stable', 'stable', 'stable');
    }
}
