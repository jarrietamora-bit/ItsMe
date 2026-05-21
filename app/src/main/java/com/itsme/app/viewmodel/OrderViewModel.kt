package com.itsme.app.viewmodel
import android.app.Application
import androidx.lifecycle.*
import com.itsme.app.ItsmeApplication
import com.itsme.app.data.entities.Order
import com.itsme.app.data.entities.OrderItem
import kotlinx.coroutines.launch

class OrderViewModel(app: Application) : AndroidViewModel(app) {
    private val dao = (app as ItsmeApplication).database.orderDao()
    private val _tid = MutableLiveData(0)
    val orders: LiveData<List<Order>> = _tid.switchMap { if (it > 0) dao.getByTravel(it) else MutableLiveData(emptyList()) }
    fun setTravel(id: Int) { _tid.value = id }
    fun items(oid: Int) = dao.getItems(oid)
    fun deleteOrder(o: Order) = viewModelScope.launch { dao.deleteItems(o.id); dao.deleteOrder(o) }
    fun insertItem(i: OrderItem) = viewModelScope.launch { dao.insertItem(i); dao.recalcTotal(i.orderId) }
    fun deleteItem(i: OrderItem) = viewModelScope.launch { dao.deleteItem(i); dao.recalcTotal(i.orderId) }
    suspend fun getOrCreate(clientId: Int, travelId: Int): Order {
        val existing = dao.getByClientAndTravel(clientId, travelId)
        if (existing != null) return existing
        val id = dao.insertOrder(Order(clientId = clientId, travelDateId = travelId))
        return Order(id = id.toInt(), clientId = clientId, travelDateId = travelId)
    }
}
