package com.itsme.app.data.dao
import androidx.lifecycle.LiveData
import androidx.room.*
import com.itsme.app.data.entities.Order
import com.itsme.app.data.entities.OrderItem

@Dao
interface OrderDao {
    @Query("SELECT * FROM orders WHERE travelDateId = :tid")
    fun getByTravel(tid: Int): LiveData<List<Order>>

    @Query("SELECT * FROM orders WHERE clientId = :cid AND travelDateId = :tid LIMIT 1")
    suspend fun getByClientAndTravel(cid: Int, tid: Int): Order?

    @Query("SELECT * FROM order_items WHERE orderId = :oid")
    fun getItems(oid: Int): LiveData<List<OrderItem>>

    @Query("SELECT * FROM order_items WHERE orderId = :oid")
    suspend fun getItemsSync(oid: Int): List<OrderItem>

    @Insert suspend fun insertOrder(o: Order): Long
    @Update suspend fun updateOrder(o: Order)
    @Delete suspend fun deleteOrder(o: Order)

    @Insert suspend fun insertItem(i: OrderItem): Long
    @Update suspend fun updateItem(i: OrderItem)
    @Delete suspend fun deleteItem(i: OrderItem)

    @Query("DELETE FROM order_items WHERE orderId = :oid")
    suspend fun deleteItems(oid: Int)

    @Query("UPDATE orders SET totalAmount = (SELECT COALESCE(SUM(salePrice * quantity),0) FROM order_items WHERE orderId = :oid) WHERE id = :oid")
    suspend fun recalcTotal(oid: Int)
}
