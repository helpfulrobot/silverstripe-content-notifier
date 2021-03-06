<?php

namespace SilverStripe\ContentNotifier\Model;

use SilverStripe\ContentNotifier\Extensions\ContentNotifierExtension;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Object;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\GroupedList;

class ContentNotifierEmail extends Object
{
    /**
     * @var Email
     */
    protected $emailer;

    /**
     * @var DataList
     */
    protected $records;

    public function __construct()
    {
        $this->emailer = Email::create();
        $config = $this->config();

        $this->emailer->setFrom($config->from);
        $this->emailer->setTo($config->to);
        $this->emailer->setSubject($config->subject);
        $this->emailer->setTemplate($config->template);
    }

    /**
     * @param DataList $list
     * @return $this
     */
    public function setRecords(DataList $list)
    {
        $this->records = $list;

        return $this;
    }

    public function send()
    {
        if (!$this->records) {
            $this->setRecords(ContentNotifierQueue::get_unnotified());
        }

        ContentNotifierExtension::disable_filtering();

        $total = $this->records->count();
        $grouped = GroupedList::create(
            $this->records->limit($this->config()->items_limit)
        )->GroupedBy('Category');

        $this->emailer->populateTemplate(array(
            'Headline' => $this->config()->headline,
            'GroupedItems' => $grouped,
            'Total' => $total,
            'Link' => Controller::join_links(
                Director::absoluteBaseURL(),
                'admin',
                'content-notifications'
            )
        ));

        $this->emailer->send();

        foreach ($this->records as $record) {
            $record->HasNotified = true;
            $record->write();
        }

        ContentNotifierExtension::enable_filtering(true);
    }
}
