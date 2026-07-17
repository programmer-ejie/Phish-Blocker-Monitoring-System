-- Build operational records directly from extension logs so a fresh system
-- does not depend on demonstration seed data.

create or replace function public.register_monitoring_campus()
returns trigger
language plpgsql
security definer
set search_path = public
as $$
begin
    if new.campus_name is not null and btrim(new.campus_name) <> '' then
        insert into public.campuses (
            code, name, computer_count, uptime_percentage, status,
            is_active, last_sync_at, created_at, updated_at
        ) values (
            new.campus_name, new.campus_name, 0, 100, 'Healthy',
            true, now(), now(), now()
        )
        on conflict (code) do update
        set is_active = true,
            last_sync_at = now(),
            updated_at = now()
        returning id into new.campus_id;
    end if;

    return new;
end;
$$;

create or replace function public.create_monitoring_alert()
returns trigger
language plpgsql
security definer
set search_path = public
as $$
declare
    alert_severity text;
    alert_status text;
    alert_title text;
begin
    if new.status = 'Proceed' then
        return new;
    end if;

    if new.risk_level = 'Critical' then
        alert_severity := 'Critical';
        alert_status := 'Open';
        alert_title := 'Critical phishing URL detected';
    elsif new.status = 'Block' or new.risk_level = 'High' then
        alert_severity := 'High';
        alert_status := 'Open';
        alert_title := 'Blocked phishing URL detected';
    else
        alert_severity := 'Medium';
        alert_status := 'Monitoring';
        alert_title := 'Suspicious URL detected';
    end if;

    if new.campus_id is not null then
        update public.campuses
        set computer_count = (
                select count(distinct computer_number)
                from public.phishing_logs
                where campus_id = new.campus_id
                  and computer_number is not null
            ),
            last_sync_at = now(),
            updated_at = now()
        where id = new.campus_id;
    end if;

    insert into public.alerts (
        campus_id, title, severity, owner, status, created_at, updated_at
    ) values (
        new.campus_id, alert_title, alert_severity, 'Automated Detection', alert_status, now(), now()
    );

    return new;
end;
$$;

drop trigger if exists register_monitoring_campus_before_insert on public.phishing_logs;
create trigger register_monitoring_campus_before_insert
before insert on public.phishing_logs
for each row execute function public.register_monitoring_campus();

drop trigger if exists create_monitoring_alert_after_insert on public.phishing_logs;
create trigger create_monitoring_alert_after_insert
after insert on public.phishing_logs
for each row execute function public.create_monitoring_alert();
