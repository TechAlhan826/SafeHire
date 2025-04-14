const mongoose = require("mongoose");

const connectDb = () => {
  mongoose
    .connect(process.env.SAFEHIRE_DB_URL, {
      useNewUrlParser: true,
      useUnifiedTopology: true,
    })
    .then(console.log("Db Is Connected"))
    .catch((err) => {
      console.log("Db Connection issue");
      console.log("====================================");
      console.log(err);
      console.log("====================================");
    });
};

module.exports = connectDb;
