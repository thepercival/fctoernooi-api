-- PRE PRE PRE doctrine-update =============================================================
-- DO SQL WITH HAND
-- ALTER TABLE rounds CHANGE numberid structureCellId INT NOT NULL;
-- TO
-- ALTER TABLE rounds ADD structureCellId INT  NULL;

-- POST POST POST doctrine-update ===========================================================

insert into categories(number, name, competitionId) (select 1, 'standaard', competitionId
                                                     from roundNumbers
                                                     where previousId is null);

insert into structureCells(categoryId, roundNumberId) (select c.id, rn.id
                                                       from roundNumbers rn
                                                                join categories c on c.competitionId = rn.competitionId);

update rounds r
    join roundNumbers rn on r.numberId = rn.id
    join categories c on c.competitionId = rn.competitionId
set structureCellId = (select id from structureCells where roundNumberId = rn.id and categoryId = c.id);

update competitors
set categoryNr = 1;

update recesses
set name = 'pauze';

-- insert into creditActions(userId, action, nrOfCredits, atDateTime) (select id, 'CreateAccountReward', 3, CURRENT_TIMESTAMP from users);

-- set input MinNrOfBatches
# update planningInputs set recreatedAt = null;
# update planningInputs set seekingPercentage = -1;
# update planningInputs set minNrOfBatches = (select min(nrOfBatches) from plannings p where p.inputId = planningInputs.id and p.state = 2);



