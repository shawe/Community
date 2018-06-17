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

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Plugins\webportal\Lib\WebPortal\SectionController;
use FacturaScripts\Plugins\Community\Model\Issue;
use FacturaScripts\Plugins\Community\Model\IssueComment;
use FacturaScripts\Plugins\Community\Model\WebTeamMember;
use Symfony\Component\HttpFoundation\Response;

/**
 * Description of EditIssue
 *
 * @author carlos
 */
class EditIssue extends SectionController
{

    /**
     *
     * @var Issue
     */
    protected $issue;

    public function getGravatar(string $email, int $size = 80): string
    {
        return "https://www.gravatar.com/avatar/" . md5(strtolower(trim($email))) . "?s=" . $size;
    }

    public function getIssue(): Issue
    {
        if (isset($this->issue)) {
            return $this->issue;
        }

        $issue = new Issue();
        $code = $this->request->get('code', '');
        if (!empty($code)) {
            $issue->loadFromCode($code);
            return $issue;
        }

        $uri = explode('/', $this->uri);
        $issue->loadFromCode(end($uri));
        return $issue;
    }

    public function obfuscateEmail(string $email): string
    {
        $aux = explode('@', $email);
        return (count($aux) == 2) ? $aux[0] . '_' . substr(md5($aux[1]), 0, 6) : '-';
    }

    protected function addNewComment(): bool
    {
        if (!$this->contactCanEdit()) {
            return false;
        }

        $close = $this->request->request->get('close', '');
        $text = $this->request->get('newComment', '');
        if (empty($text) && $close === 'TRUE') {
            $text = $this->i18n->trans('close');
        }

        if (empty($text)) {
            return false;
        }

        $issue = $this->getIssue();
        $comment = new IssueComment();
        $comment->body = $text;
        $comment->idcontacto = $this->contact->idcontacto;
        $comment->idissue = $issue->idissue;
        if (!$comment->save()) {
            $this->miniLog->alert($this->i18n->trans('record-save-error'));
            return false;
        }

        $this->miniLog->notice($this->i18n->trans('record-updated-correctly'));
        $issue->lastcommidcontacto = $this->contact->idcontacto;
        $issue->closed = ($close === 'TRUE') ? true : $issue->closed;
        $issue->save();
        return true;
    }

    protected function contactCanEdit(): bool
    {
        if (null === $this->contact) {
            return false;
        }

        $issue = $this->getIssue();
        if ($issue->idcontacto === $this->contact->idcontacto) {
            return true;
        }

        $member = new WebTeamMember();
        $where = [
            new DataBaseWhere('idcontacto', $this->contact->idcontacto),
            new DataBaseWhere('idteam', $issue->idteam),
            new DataBaseWhere('accepted', true)
        ];

        return $member->loadFromCode('', $where);
    }

    protected function contactCanSee(): bool
    {
        if ($this->contactCanEdit()) {
            return true;
        }

        return false;
    }

    protected function createSections()
    {
        $this->addSection('issue', ['fixed' => true, 'template' => 'Section/Issue']);

        $this->addListSection('comments', 'IssueComment', 'Section/IssueComments', 'comments', 'fa-comments');
        $this->addOrderOption('comments', 'creationdate', 'date');
        $this->addButton('comments', $this->getIssue()->url('public'), '', 'fa-refresh');
    }

    protected function execPreviousAction(string $action)
    {
        switch ($action) {
            case 'new-comment':
                $this->addNewComment();
                return true;

            case 're-open':
                $this->reopenAction();
                return true;
        }

        return parent::execPreviousAction($action);
    }

    protected function loadData(string $sectionName)
    {
        switch ($sectionName) {
            case 'comments':
                $issue = $this->getIssue();
                $where = [new DataBaseWhere('idissue', $issue->idissue)];
                $this->loadListSection($sectionName, $where);
                break;

            case 'issue':
                $this->loadIssue();
                break;
        }
    }

    protected function loadIssue()
    {
        $this->issue = $this->getIssue();
        if (!$this->issue->exists()) {
            $this->miniLog->alert($this->i18n->trans('no-data'));
            $this->response->setStatusCode(Response::HTTP_NOT_FOUND);
            $this->webPage->noindex = true;
            return;
        }

        if (!$this->contactCanSee()) {
            $this->miniLog->alert($this->i18n->trans('access-denied'));
            $this->response->setStatusCode(Response::HTTP_FORBIDDEN);
            $this->webPage->noindex = true;

            $template = (null === $this->contact) ? 'Master/LoginToContinue' : 'Master/AccessDenied';
            $this->setTemplate($template);
            return;
        }

        $this->title = 'Issue #' . $this->issue->idissue;
        $this->description = $this->issue->description();
        $this->issue->increaseVisitCount($this->request->getClientIp());
    }

    protected function reopenAction()
    {
        if ($this->contactCanEdit()) {
            $issue = $this->getIssue();
            $issue->closed = false;
            $issue->save();
        }
    }
}
