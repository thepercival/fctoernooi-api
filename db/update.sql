-- PRE PRE PRE doctrine-update =============================================================
-- DO SQL WITH HAND
-- ALTER TABLE rounds CHANGE numberid structureCellId INT NOT NULL;
-- TO
-- ALTER TABLE rounds ADD structureCellId INT  NULL;

-- POST POST POST doctrine-update ===========================================================
update tournaments set intro = 'Dit is een standaard welkomsbericht. Je kunt het welkomstbericht aanpassen, de huisregels opgeven, een locatie opgeven en sponsoren tonen.' where intro = '' and public = 1;



