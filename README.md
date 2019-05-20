## Kimai import plugin

This creates a console command that imports timesheets into Kimai.

It reads a CSV file and creates a timesheet for each line it finds.  If the Customer, Project or Activity does not exist it will create one.  The deafult password for a new user is scrambled (but not fully secured).  Use the password reset to set that.

The fields in the CSV are:

  * id, The UID for the row.  This is used to log each row imported.
  * customer name, The name of the customer.
  * project name, The name of the project
  * activity name, The name of the activity
  * start, The sate of the activity in the format YYYY-MM-DD HH:MM:SS
  * duration, In minutes
  * description, The text description of the timesheet entry
  * user name, The username of the logger
  * email, The email of the logger

Newly created projects are assigned the customer specified by the line.  Activities are assigned to the Project specified by the line.

### Getting the data

To get timesheets out of an existing kimai then use a version of this SQL:

    select
        t.id          as id,
        c.name        as customer,
        p.name        as project,
        a.name        as activity,
        t.start_time  as date,
        t.duration    as duration,
        t.description as title,
        u.username    as username,
        u.email       as email
    from
        kimai2_timesheet t
        inner join kimai2_users u on t.user=u.id
        inner join kimai2_activities a on t.activity_id=a.id
        inner join kimai2_projects p on a.project_id=p.id
        inner join kimai2_customers c on p.customer_id=c.id;

