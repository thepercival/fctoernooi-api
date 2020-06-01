delete
from tournamentroles
where role in (4);

update tournamentroles
set role = 4
where role = 8;

update tournamentroles
set role = 8
where role = 16;

-- adding role-column of admin-role-row
update tournamentroles tr
set tr.role = tr.role + ((select sum(trsubsel.role)
                          from tournamentroles trsubsel
                          where trsubsel.tournamentid = tr.tournamentid
                            and trsubsel.userid = tr.userid
                            and trsubsel.role > tr.role))
where tr.role = 1;

-- removing rows no-admin but has an admin-row
delete
from tournamentroles
where (role & 1) = 0
  and exists(select *
             from tournamentroles trsub
             where trsub.tournamentid = tournamentroles.tournamentid
               and trsub.userid = tournamentroles.userid
               and trsub.id <> tournamentroles.id
               and (trsub.role & 1) = 1);

-- removing obsolete referee roles
delete tr
from tournamentroles tr
         join tournaments t on t.id = tr.tournamentid
         join users u on u.id = tr.userid
         left join referees r on r.emailaddress = u.emailaddress and t.competitionid = r.competitionid
where (tr.role & 8) = 8
  and tr.role = 8
  and r.emailaddress is null;

-- update obsolete referee roles
update tournamentroles tr join tournaments t on t.id = tr.tournamentid join users u on u.id = tr.userid left join referees r on r.emailaddress = u.emailaddress and t.competitionid = r.competitionid
set tr.role = tr.role - 8
where (tr.role & 8) = 8
  and tr.role > 8
  and r.emailaddress is null;