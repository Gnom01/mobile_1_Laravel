<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\ScheduleChangesController;
use App\Models\CrmUser;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Pilnuje semantyki resolveScopeUserIds w OBU kopiach resolvera
 * (CalendarController i ScheduleChangesController):
 *
 *  - własne ID zalogowanego ZAWSZE wchodzi do domyślnego zakresu — CRM
 *    tworzy relacje odwrotne (Parent_UsersID = dziecko -> rodzic), przez
 *    które konto dziecka "ma pod sobą" rodzica; bez self zakres degenerował
 *    się do [ID rodzica] i kalendarz/odwołania dziecka były puste,
 *  - personGuid nadal jawnie zawęża do jednej osoby (self albo powiązanej),
 *  - obcy personGuid odrzucany (null -> 403 w kontrolerze).
 *
 * Test czysto obiektowy — bez bazy danych.
 */
class CalendarScopeResolutionTest extends TestCase
{
    /** @return array<class-string> */
    public static function controllerProvider(): array
    {
        return [
            'CalendarController' => [CalendarController::class],
            'ScheduleChangesController' => [ScheduleChangesController::class],
        ];
    }

    private function resolve(string $controllerClass, CrmUser $auth, Collection $related, ?string $personGuid): ?array
    {
        $method = new ReflectionMethod($controllerClass, 'resolveScopeUserIds');
        $method->setAccessible(true);

        return $method->invoke(
            (new \ReflectionClass($controllerClass))->newInstanceWithoutConstructor(),
            $auth,
            $auth, // parentUser == authUser (wymusza to resolveParentContext)
            $related,
            $personGuid
        );
    }

    private function user(int $usersId, string $guid): CrmUser
    {
        $user = new CrmUser();
        $user->UsersID = $usersId;
        $user->guid = $guid;

        return $user;
    }

    private function relatedRow(int $usersId, string $guid): object
    {
        return (object) ['UsersID' => $usersId, 'guid' => $guid];
    }

    /** @dataProvider controllerProvider */
    public function test_child_with_reverse_relation_keeps_own_id_in_scope(string $controllerClass): void
    {
        // Konto dziecka (100) z odwrotną relacją do rodzica (200).
        $child = $this->user(100, 'child-guid');
        $related = collect([$this->relatedRow(200, 'parent-guid')]);

        $scope = $this->resolve($controllerClass, $child, $related, null);

        $this->assertContains(100, $scope, 'Własne ID dziecka musi być w zakresie — inaczej szukamy zajęć po umowach rodzica.');
        $this->assertContains(200, $scope);
    }

    /** @dataProvider controllerProvider */
    public function test_parent_default_scope_includes_children_and_self(string $controllerClass): void
    {
        $parent = $this->user(200, 'parent-guid');
        $related = collect([
            $this->relatedRow(101, 'child-a'),
            $this->relatedRow(102, 'child-b'),
        ]);

        $scope = $this->resolve($controllerClass, $parent, $related, null);

        $this->assertContains(101, $scope);
        $this->assertContains(102, $scope);
        $this->assertContains(200, $scope, 'Self w zakresie: rodzic-uczestnik i gałąź instruktorska (in_array(auth, scope)).');
        $this->assertSame($scope, array_values(array_unique($scope)), 'Zakres bez duplikatów.');
    }

    /** @dataProvider controllerProvider */
    public function test_no_relations_falls_back_to_self(string $controllerClass): void
    {
        $user = $this->user(100, 'solo-guid');

        $scope = $this->resolve($controllerClass, $user, collect(), null);

        $this->assertSame([100], $scope);
    }

    /** @dataProvider controllerProvider */
    public function test_person_guid_self_narrows_to_self(string $controllerClass): void
    {
        $user = $this->user(100, 'self-guid');
        $related = collect([$this->relatedRow(200, 'parent-guid')]);

        $scope = $this->resolve($controllerClass, $user, $related, 'self-guid');

        $this->assertSame([100], $scope, 'Jawne personGuid=self to tor widoku dziecka — wyłącznie własne ID.');
    }

    /** @dataProvider controllerProvider */
    public function test_person_guid_related_narrows_to_that_person(string $controllerClass): void
    {
        $parent = $this->user(200, 'parent-guid');
        $related = collect([$this->relatedRow(101, 'child-a')]);

        $scope = $this->resolve($controllerClass, $parent, $related, 'child-a');

        $this->assertSame([101], $scope);
    }

    /** @dataProvider controllerProvider */
    public function test_unknown_person_guid_is_rejected(string $controllerClass): void
    {
        $parent = $this->user(200, 'parent-guid');
        $related = collect([$this->relatedRow(101, 'child-a')]);

        $scope = $this->resolve($controllerClass, $parent, $related, 'stranger-guid');

        $this->assertNull($scope, 'Obcy personGuid musi kończyć się odmową (403 w kontrolerze).');
    }
}
