-- PRE PRE PRE doctrine-update =============================================================
update tournaments
set exported = 0;

update sports
set customId = 18
where name = 'rugby';

delete
from planningConfigs
where not exists(select * from roundNumbers rn where rn.planningConfigId = planningConfigs.id);

delete plbase
from plannings plbase
         join (select pl.id
               from plannings pl
               where pl.maxNrOfGamesInARow > 0
                 and exists(select *
                            from plannings plLess
                            where plLess.inputId = pl.inputId
                              and plLess.minNrOfBatchGames = pl.minNrOfBatchGames
                              and plLess.maxNrOfBatchGames = pl.maxNrOfBatchGames
                              and plLess.maxNrOfGamesInARow > pl.maxNrOfGamesInARow
                              and pl.state = 2
                              and pl.nrOfBatches >= plLess.nrOfBatches)) plTmp on plTmp.id = plbase.id;

-- POST POST POST doctrine-update ===========================================================
delete pcbase
from planningConfigs pcbase
         join (select rn.planningConfigId
               from roundNumbers rn
                        join planningConfigs pc on rn.planningConfigId = pc.id
               where (select count(*)
                      from roundNumbers rnsub
                               join planningConfigs pcsub
                                    on rnsub.planningConfigId = pcsub.id and rnsub.number < rn.number and
                                       rnsub.competitionId = rn.competitionId
                      where pcsub.enableTime = pc.enableTime
                        and pcsub.minutesPerGame = pc.minutesPerGame
                        and pcsub.minutesPerGameExt = pc.minutesPerGameExt
                        and pcsub.minutesInBetween = pc.minutesInBetween
                        and pcsub.minutesBetweenGames = pc.minutesBetweenGames
                        and pcsub.selfReferee = pc.selfReferee
                        and pcsub.extension = pc.extension
                        and pcsub.editMode = pc.editMode)) rnbase on rnbase.planningConfigId = pcbase.id;


-- insert into creditActions(userId, action, nrOfCredits, atDateTime) (select id, 'CreateAccountReward', 3, CURRENT_TIMESTAMP from users);

-- set input MinNrOfBatches
# update planningInputs set recreatedAt = null;
# update planningInputs set seekingPercentage = -1;
# update planningInputs set minNrOfBatches = (select min(nrOfBatches) from plannings p where p.inputId = planningInputs.id and p.state = 2);



