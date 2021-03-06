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

use FacturaScripts\Core\App\AppSettings;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Plugins\Community\Model\WebDocPage;
use FacturaScripts\Plugins\Community\Model\WebProject;
use FacturaScripts\Plugins\webportal\Controller\WebSearch as ParentSearch;

/**
 * Description of WebSearch
 *
 * @author Carlos García Gómez <carlos@facturascripts.com>
 */
class WebSearch extends ParentSearch
{

    /**
     * Search the query on pages/plugins.
     *
     * @param string $query
     */
    protected function search(string $query)
    {
        parent::search($query);
        $this->searchDocPages($query);
        $this->searchPlugins($query);
    }

    /**
     * Search the query on pages.
     *
     * @param string $query
     */
    protected function searchDocPages(string $query)
    {
        $defaultIdproject = AppSettings::get('community', 'idproject', '');

        $docPage = new WebDocPage();
        $where = [new DataBaseWhere('body|title', $query, 'LIKE')];
        foreach ($docPage->all($where, ['visitcount' => 'DESC']) as $docPage) {
            $this->addSearchResults([
                'icon' => 'fa-book',
                'title' => $docPage->title,
                'description' => $docPage->body,
                'link' => $docPage->url('public'),
                'priority' => ($docPage->idproject == $defaultIdproject) ? 0 : -1,
                ], $query);
        }
    }

    /**
     * Search the query on the plugins.
     *
     * @param string $query
     */
    protected function searchPlugins(string $query)
    {
        $pluginProject = new WebProject();
        $where = [
            new DataBaseWhere('name|description', $query, 'LIKE'),
            new DataBaseWhere('plugin', true)
        ];
        foreach ($pluginProject->all($where) as $plugin) {
            $this->addSearchResults([
                'icon' => 'fa-plug',
                'title' => $plugin->name,
                'description' => $plugin->description,
                'link' => $plugin->url('public')
                ], $query);
        }
    }
}
