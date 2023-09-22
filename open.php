<?php

/*********************************************************************
    open.php

    New tickets handle.

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
 **********************************************************************/
require('client.inc.php');
require_once INCLUDE_DIR . 'class.organization.php';
define('SOURCE', 'Web'); //Ticket source.
$ticket = null;
$errors = array();

session_start();

// Check if the '_auth' session variable exists
if (isset($_SESSION['_auth'])) {
    $authValue = $_SESSION['_auth'];

    // Check if 'user' key exists within the '_auth' array
    if (isset($authValue['user']) && is_array($authValue['user'])) {
        // Check if 'client' key exists within the 'user' sub-array
        if (isset($authValue['user']['id'])) {
            // Extract and echo the value of 'client' after removing 'client:' prefix
            $clientValue = $authValue['user']['key'];
            $clientValueWithoutPrefix = str_replace('client:', '', $clientValue);

            // Lookup the organization
            $org = OrganizationCdata::lookup($clientValueWithoutPrefix);

            // Get the current date and time
            $currentDate = new DateTime();

            // Compare the end date with the current date
            $slaEndDate = new DateTime($org->sla_end_date);

            if ($currentDate > $slaEndDate) {
                // End date has passed, throw an error
                echo "<div style='text-align: center; font-weight: bold; color: red; font-size: 24px;'>SLA End Date has passed!</div>";

                // Exit to keep the rest of the page blank
                exit();
            } else {
                // End date is in the future, continue with your code
                echo $org->sla_end_date;
            }
        } else {
            echo "The 'client' key exists within 'user' sub-array but does not have a value.";
        }
    } else {
        echo "'user' key does not exist or is not an array within '_auth' session variable.";
    }
} else {
    echo "The '_auth' session variable does not exist.";
}





if ($_POST) {
    $vars = $_POST;
    $vars['deptId'] = $vars['emailId'] = 0; //Just Making sure we don't accept crap...only topicId is expected.
    if ($thisclient) {
        $vars['uid'] = $thisclient->getId();
    } elseif ($cfg->isCaptchaEnabled()) {
        if (!$_POST['captcha'])
            $errors['captcha'] = __('Enter text shown on the image');
        elseif (strcmp($_SESSION['captcha'], md5(strtoupper($_POST['captcha']))))
            $errors['captcha'] = sprintf('%s - %s', __('Invalid'), __('Please try again!'));
    }

    $tform = TicketForm::objects()->one()->getForm($vars);
    $messageField = $tform->getField('message');
    $attachments = $messageField->getWidget()->getAttachments();
    if (!$errors) {
        $vars['message'] = $messageField->getClean();
        if ($messageField->isAttachmentsEnabled())
            $vars['files'] = $attachments->getFiles();
    }

    // Drop the draft.. If there are validation errors, the content
    // submitted will be displayed back to the user
    Draft::deleteForNamespace('ticket.client.' . substr(session_id(), -12));
    //Ticket::create...checks for errors..
    if (($ticket = Ticket::create($vars, $errors, SOURCE))) {
        $msg = __('Support ticket request created');
        // Drop session-backed form data
        unset($_SESSION[':form-data']);
        //Logged in...simply view the newly created ticket.
        if ($thisclient && $thisclient->isValid()) {
            // Regenerate session id
            $thisclient->regenerateSession();
            @header('Location: tickets.php?id=' . $ticket->getId());
        } else
            $ost->getCSRF()->rotate();
    } else {
        $errors['err'] = $errors['err'] ?: sprintf(
            '%s %s',
            __('Unable to create a ticket.'),
            __('Correct any errors below and try again.')
        );
    }
}

//page
$nav->setActiveNav('new');
if ($cfg->isClientLoginRequired()) {
    if ($cfg->getClientRegistrationMode() == 'disabled') {
        Http::redirect('view.php');
    } elseif (!$thisclient) {
        require_once 'secure.inc.php';
    } elseif ($thisclient->isGuest()) {
        require_once 'login.php';
        exit();
    }
}

require(CLIENTINC_DIR . 'header.inc.php');
if (
    $ticket
    && (
        (($topic = $ticket->getTopic()) && ($page = $topic->getPage()))
        || ($page = $cfg->getThankYouPage())
    )
) {
    // Thank the user and promise speedy resolution!
    echo Format::viewableImages(
        $ticket->replaceVars(
            $page->getLocalBody()
        ),
        ['type' => 'P']
    );
} else {
    require(CLIENTINC_DIR . 'open.inc.php');
}
require(CLIENTINC_DIR . 'footer.inc.php');
