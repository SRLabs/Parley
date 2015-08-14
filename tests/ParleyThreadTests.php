<?php

use Illuminate\Database\Eloquent;
use Illuminate\Support\Collection;
use Parley\Models\Thread;
use Parley\Exceptions\NonParleyableMemberException;
use Chekhov\User;
use Chekhov\Widget;

class ParleyThreadTests extends ParleyTestCase
{
    public function test_creating_a_thread()
    {
        $parley = $this->simulate_a_conversation('Happy Name Day!');

        $this->assertInstanceOf('Parley\Models\Thread', $parley);
        $this->assertEquals($parley->subject, 'Happy Name Day!');
    }

    public function test_adding_member_to_thread()
    {
        $parley = $this->simulate_a_conversation('Happy Name Day!');
        $parley->addMember($this->prozorovGroup);

        $members = $parley->getMembers();

        $this->assertEquals($members->count(), 3);
    }

    public function test_adding_members_via_with_participants_method()
    {
        $this->expectsEvents(Parley\Events\ParleyThreadCreated::class);

        $parley = Parley::discuss([
            'subject'  => 'Happy Name Day!',
            'body'   => 'Congratulations on your 20th name day!',
            'alias'  => $this->nikolai->alias,
            'author' => $this->nikolai
        ])->withParticipants([$this->irina, $this->prozorovGroup]);

        $members = $parley->getMembers();

        $this->assertEquals($members->count(), 3);
    }

    /**
     * @expectedException Parley\Exceptions\NonParleyableMemberException
     */
    public function test_adding_nonparleyable_object_as_member()
    {
        $widget = Widget::create(['name' => 'Gift']);

        Parley::discuss([
            'subject'  => 'Happy Name Day!',
            'body'   => 'Congratulations on your 20th name day!',
            'alias'  => $this->nikolai->alias,
            'author' => $this->nikolai
        ])->withParticipants($widget);
    }

    public function test_removing_member_from_thread()
    {
        $parley = Parley::discuss([
            'subject'  => 'Happy Name Day!',
            'body'   => 'Congratulations on your 20th name day!',
            'alias'  => $this->nikolai->alias,
            'author' => $this->nikolai
        ])->withParticipants([$this->irina, $this->prozorovGroup]);

        $parley->removeMember($this->prozorovGroup);

        $members = $parley->getMembers();

        $this->assertEquals($members->count(), 2);
    }

    public function test_validating_thread_membership()
    {
        $parley = $this->simulate_a_conversation('Happy Name Day!');

        $this->assertTrue($parley->isMember($this->irina));
        $this->assertFalse($parley->isMember($this->prozorovGroup));
    }

    public function test_adding_and_removing_reference_object()
    {
        $pencils = Widget::create(['name' => 'Pencils']);
        $penknife = Widget::create(['name' => 'Penknife']);

        $parley = Parley::discuss([
            'subject'  => 'Happy Name Day!',
            'body'   => 'Congratulations on your 20th name day!',
            'alias'  => $this->nikolai->alias,
            'author' => $this->nikolai
        ], $pencils)->withParticipants([$this->irina, $this->prozorovGroup]);

        $originalReferenceObject = $parley->getReferenceObject();

        $parley->clearReferenceObject();
        $removedReferenceObject = $parley->getReferenceObject();

        $parley->setReferenceObject($penknife);
        $newReferenceObject = $parley->getReferenceObject();

        $this->assertInstanceOf('Chekhov\Widget', $originalReferenceObject);
        $this->assertEquals('Pencils', $originalReferenceObject->name);
        $this->assertNull($removedReferenceObject);
        $this->assertInstanceOf('Chekhov\Widget', $newReferenceObject);
        $this->assertEquals('Penknife', $newReferenceObject->name);
    }

    /**
     * @expectedException Parley\Exceptions\NonReferableObjectException
     */
    public function test_adding_object_without_id_as_reference_object()
    {
        $widget = new Widget();

        $parley = Parley::discuss([
            'subject'  => 'Happy Name Day!',
            'body'   => 'Congratulations on your 20th name day!',
            'alias'  => $this->nikolai->alias,
            'author' => $this->nikolai
        ], $widget)->withParticipants($this->irina);
    }

    public function test_opening_and_closing_a_thread()
    {
        $parley = Parley::discuss([
            'subject'  => 'Happy Name Day!',
            'body'   => 'Congratulations on your 20th name day!',
            'alias'  => $this->nikolai->alias,
            'author' => $this->nikolai
        ])->withParticipants([$this->irina, $this->prozorovGroup]);

        $parley->closedBy($this->irina);
        $shouldBeClosed = $parley->isClosed();

        $parley->reopen();
        $shouldBeOpen = $parley->isClosed();

        $this->assertTrue($shouldBeClosed);
        $this->assertFalse($shouldBeOpen);
    }

    public function test_retrieving_member_who_closed_a_thread()
    {
        $parley = Parley::discuss([
            'subject'  => 'Happy Name Day!',
            'body'   => 'Congratulations on your 20th name day!',
            'alias'  => $this->nikolai->alias,
            'author' => $this->nikolai
        ])->withParticipants([$this->irina, $this->prozorovGroup]);

        $parley->closedBy($this->irina);
        $closer = $parley->getCloser();

        $this->assertTrue($parley->isClosed());
        $this->assertInstanceOf('Chekhov\User', $closer);
        $this->assertEquals('Irina Prozorovna', $closer->alias);
    }

    public function test_retrieving_the_newest_message_in_a_thread()
    {
        $parley = $this->simulate_a_conversation();
        sleep(1);
        $parley->reply([
            'body'   => "Nonsense - you should be celebrating!",
            'author' => $this->nikolai
        ]);

        $message = $parley->newestMessage();

        $this->assertInstanceOf('Parley\Models\Message', $message);
        $this->assertEquals("Nonsense - you should be celebrating!", $message->body);
    }

    public function test_retrieving_the_original_message_in_a_thread()
    {
        $parley = $this->simulate_a_conversation();
        sleep(1);
        $parley->reply([
            'body'   => "Nonsense - you should be celebrating!",
            'author' => $this->nikolai
        ]);

        $message = $parley->originalMessage();

        $this->assertInstanceOf('Parley\Models\Message', $message);
        $this->assertEquals("Congratulations on your 20th name day!", $message->body);
    }

    public function test_retrieving_all_thread_messages()
    {
        $parley = $this->simulate_a_conversation();
        sleep(1);
        $parley->reply([
            'body'   => "Nonsense - you should be celebrating!",
            'author' => $this->nikolai
        ]);

        $messages = $parley->messages;

        $this->assertInstanceOf('Illuminate\Support\Collection', $messages);
        $this->assertEquals(3, $messages->count());
    }

    public function test_marking_read_and_unread_for_individual_members()
    {
        $parley = $this->simulate_a_conversation();

        $irinaHasReadA = $parley->hasBeenReadByMember($this->irina);  // Should be true
        $nikolaiHasReadA = $parley->hasBeenReadByMember($this->nikolai); // Should be false

        $parley->reply([
            'body'   => "Nonsense - you should be celebrating!",
            'author' => $this->nikolai
        ]);

        $irinaHasReadB = $parley->hasBeenReadByMember($this->irina);  // Should be false
        $nikolaiHasReadB = $parley->hasBeenReadByMember($this->nikolai); // Should be true

        $parley->markUnreadForMembers($this->nikolai);
        $nikolaiHasReadC = $parley->hasBeenReadByMember($this->nikolai); // Should be false

        $parley->markReadForMembers($this->irina);
        $irinaHasReadC =  $parley->hasBeenReadByMember($this->irina);  // Should be true

        $this->assertTrue($irinaHasReadA);
        $this->assertFalse($nikolaiHasReadA);
        $this->assertFalse($irinaHasReadB);
        $this->assertTrue($nikolaiHasReadB);
        $this->assertTrue($irinaHasReadC);
        $this->assertFalse($nikolaiHasReadC);
    }

    public function test_retrieving_all_thread_members()
    {
        $parley = $this->simulate_a_conversation();
        $members = $parley->getMembers();

        $this->assertInstanceOf('Illuminate\Support\Collection', $members);
        $this->assertEquals(2, $members->count());
    }

    public function test_retrieving_filtered_thread_members()
    {
        $parley = $this->simulate_a_conversation();
        $members = $parley->getMembers(['except' => $this->nikolai]);

        $this->assertInstanceOf('Illuminate\Support\Collection', $members);
        $this->assertEquals(1, $members->count());
    }
}
