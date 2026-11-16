try
{
    DBConnection.OpenConnection();

    string query = "INSERT INTO Rooms" +
      "(RoomNumber, RoomType, Price)" +
      "VALUES(@number, @type, @price)";

      MySqlCommand cmd = new MySqlCommand(query, DBConnection.con);

      cmd.AddWidthValue("@number", textBox1.Text);
      cmd.AddWidthValue("@type", textBox2.Text);
      cmd.AddWidthValue("@price", textBox3.Text);

      cmd.ExecuteNonQuery();

      MessageBox("Room Added Successfully");

      DBconnection.CloseConnection();

      catch(Exception ex)
      {
        MessageBox.Show(ex.Message);
        DBConnection.CloseConnection();
      }
}



try{
    DBConnection.OpenConnection();

    string query = "UPDATE Rooms SET RoomNumber=@number, RoomType=@type, Price=@price WHERE RoomID=@id";

    MySqlCommand cmd = new MySqlCommand(query, DBConnection.con);

    cmd.AddWidthValue("@number", textBox1.Text);
    cmd.AddWidthValue("@type", textBox2.Text);
    cmd.AddWidthValue("@price", textBox3.Text);
    cmd.AddWidthValue("@id", textBox4.Text);

    cmd.ExecptionNonQuery();

    MessageBox.Show("Room Updated Successfully");

    DBConnection.CloseConnection();

    catch{
        MessageBox.Show(ex.Message);
        DBconnection.CloseConnection();

    }
}

try{
    DBConnection.OPenConnection();

    string query = "DELETE FROM Room WHERE RoomID=@id";

    MySQlCommand cmd = new MySqlCommand(query, DBConnection.con);

    cmd.Parameters.AddWidthValue("@id", textBox1.Text);

    cmd.ExecuteNonQuery();

    MessageBoc.Show("Delete Successfully");

    DBConnection.CloseConnection();

    catch{
        Message.Show(ex.Message);
        DBConnection.CloseConnection();
    }
}