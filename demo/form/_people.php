<?php
/**
 * The people of the "rows that open a side sheet" demo — shared by the list on
 * demo/index.php and by f_person_sheet.php, which fills the sheet a row opens.
 * Sorted by access, because the list groups by it.
 *
 * @return list<array{id:int,name:string,email:string,access:string,plan:string,seen:string,failing:int,open:int,done:int,login:bool}>
 */
return [
    ['id' => 1, 'name' => 'Anna Schneider', 'email' => 'anna.schneider@example.com', 'access' => 'with',    'plan' => 'Pro',   'seen' => '2 min ago',  'failing' => 20, 'open' => 75, 'done' => 88, 'login' => true],
    ['id' => 2, 'name' => 'Thomas Berger',  'email' => 'thomas.berger@example.com',  'access' => 'with',    'plan' => 'Team',  'seen' => 'just now',   'failing' => 17, 'open' => 8,  'done' => 8,  'login' => true],
    ['id' => 3, 'name' => 'Lisa Wagner',    'email' => 'lisa.wagner@example.com',    'access' => 'with',    'plan' => 'Free',  'seen' => 'today',      'failing' => 0,  'open' => 0,  'done' => 3,  'login' => true],
    ['id' => 4, 'name' => 'Peter Gruber',   'email' => 'peter.gruber@example.com',   'access' => 'without', 'plan' => 'Team',  'seen' => 'never',      'failing' => 0,  'open' => 0,  'done' => 0,  'login' => false],
    ['id' => 5, 'name' => 'Maria Huber',    'email' => 'maria.huber@example.com',    'access' => 'without', 'plan' => 'Free',  'seen' => 'never',      'failing' => 0,  'open' => 0,  'done' => 0,  'login' => false],
];
