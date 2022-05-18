-- PRE PRE PRE doctrine-update =============================================================

-- POST POST POST doctrine-update ===========================================================
insert into categories(number, name, competitionId) (select 1, 'standaard', competitionId
                                                     from roundNumbers
                                                     where previousId is null)

update rounds join roundNumbers rn on rn.id = rounds.numberId
set rounds.categoryId = (select id from categories where competitionId = rn.competitionId);

-- insert into creditActions(userId, action, nrOfCredits, atDateTime) (select id, 'CreateAccountReward', 3, CURRENT_TIMESTAMP from users);

-- set input MinNrOfBatches
# update planningInputs set recreatedAt = null;
# update planningInputs set seekingPercentage = -1;
# update planningInputs set minNrOfBatches = (select min(nrOfBatches) from plannings p where p.inputId = planningInputs.id and p.state = 2);



