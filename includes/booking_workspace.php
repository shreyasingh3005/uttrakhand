<?php
// Embedded in the existing admin and employee booking screens.
$workspaceHotels=$conn->query("SELECT h.id,h.name,r.id AS room_id,r.name AS room_name FROM hotels h JOIN hotel_room_categories r ON r.hotel_id=h.id AND r.status='active' WHERE h.status='active' ORDER BY h.name,r.name")->fetchAll();
$workspaceAgents=$conn->query("SELECT id,name,phone FROM agents_details WHERE status='Active' ORDER BY name")->fetchAll();
$workspaceEmployees=$_SESSION['role']==='admin'?$conn->query("SELECT id,username FROM users WHERE role='employee' ORDER BY username")->fetchAll():[];
?>
<style>
#crmCreateModal .modal-content > form, #crmManageModal .modal-content > form { display:flex; flex-direction:column; min-height:0; max-height:100%; }
#crmCreateModal .modal-body, #crmManageModal .modal-body { overflow-y:auto; }
#crmHistory .list-group-item { overflow-wrap:anywhere; }
</style>
<div class="d-flex flex-wrap gap-2 my-3">
  <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#crmCreateModal">New room booking</button>
  <span class="text-muted align-self-center small">Room rates and availability are checked when saved.</span>
</div>
<div class="modal fade" id="crmCreateModal" tabindex="-1" aria-labelledby="crmCreateTitle" aria-hidden="true">
 <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
 <form id="crmCreateForm">
  <div class="modal-header"><h5 id="crmCreateTitle" class="modal-title">Create booking</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body"><div class="alert d-none" role="status" id="crmCreateMessage"></div><div class="row g-3">
  <input type="hidden" name="action" value="create"><input type="hidden" name="hotelId" id="crmHotelId"><input type="hidden" name="amount" value="1"><input type="hidden" name="request_key" id="crmRequestKey">
  <div class="col-md-6"><label class="form-label" for="crmName">Customer name</label><input class="form-control" id="crmName" name="clientName" maxlength="120" required></div>
  <div class="col-md-6"><label class="form-label" for="crmPhone">Phone</label><input class="form-control" id="crmPhone" name="clientPhone" maxlength="20" required></div>
  <div class="col-md-6"><label class="form-label" for="crmEmail">Email (optional)</label><input class="form-control" type="email" id="crmEmail" name="clientEmail" maxlength="150"></div>
  <div class="col-md-6"><label class="form-label" for="crmAgent">Agent</label><select class="form-select" id="crmAgent" name="agentId" required><option value="">Select agent</option><?php foreach($workspaceAgents as $agent): ?><option value="<?= (int)$agent['id'] ?>"><?= htmlspecialchars($agent['name'].' — '.$agent['phone'],ENT_QUOTES,'UTF-8') ?></option><?php endforeach ?></select></div>
  <div class="col-md-8"><label class="form-label" for="crmRoom">Hotel / room category</label><select class="form-select" name="room_category_id" id="crmRoom" required><option value="">Select room</option><?php foreach($workspaceHotels as $hotel): ?><option value="<?= (int)$hotel['room_id'] ?>" data-hotel="<?= (int)$hotel['id'] ?>"><?= htmlspecialchars($hotel['name'].' / '.$hotel['room_name'],ENT_QUOTES,'UTF-8') ?></option><?php endforeach ?></select></div>
  <div class="col-md-4"><label class="form-label" for="crmMeal">Meal plan</label><select class="form-select" id="crmMeal" name="meal_plan"><?php foreach(['EP','CP','MAP','AP'] as $plan): ?><option><?= $plan ?></option><?php endforeach ?></select></div>
  <div class="col-md-6"><label class="form-label" for="crmIn">Check-in</label><input class="form-control" type="date" id="crmIn" name="checkIn" required></div>
  <div class="col-md-6"><label class="form-label" for="crmOut">Check-out</label><input class="form-control" type="date" id="crmOut" name="checkOut" required></div>
  <div class="col-md-4"><label class="form-label" for="crmRooms">Rooms</label><input class="form-control" type="number" id="crmRooms" name="roomCount" min="1" max="255" value="1" required></div>
  <div class="col-md-4"><label class="form-label" for="crmGuests">Guests</label><input class="form-control" type="number" id="crmGuests" name="guestCount" min="1" max="255" value="1" required></div>
  <div class="col-md-4"><label class="form-label" for="crmPaid">Amount received</label><input class="form-control" type="number" id="crmPaid" name="paidAmount" min="0" step="0.01" value="0" required></div>
  <?php if ($_SESSION['role']==='admin'): ?><div class="col-md-6"><label class="form-label" for="crmAssign">Assign employee</label><select class="form-select" id="crmAssign" name="assigned_user_id"><option value="">Unassigned</option><?php foreach($workspaceEmployees as $employee): ?><option value="<?= (int)$employee['id'] ?>"><?= htmlspecialchars($employee['username'],ENT_QUOTES,'UTF-8') ?></option><?php endforeach ?></select></div><?php endif ?>
  <div class="col-md-6"><label class="form-label" for="crmQuery">Enquiry ID (optional)</label><input class="form-control" type="number" min="1" id="crmQuery" name="query_id"></div>
  <div class="col-12"><label class="form-label" for="crmNotes">Special requests</label><textarea class="form-control" id="crmNotes" name="specialRequest" rows="2" maxlength="4000"></textarea></div>
  </div></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Close</button><button class="btn btn-primary" type="submit">Check availability & create</button></div>
 </form></div></div>
</div>
<div class="modal fade" id="crmManageModal" tabindex="-1" aria-labelledby="crmManageTitle" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
 <form id="crmManageForm"><div class="modal-header"><h5 id="crmManageTitle" class="modal-title">Booking details & history</h5><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
 <div class="modal-body"><div id="crmManageMessage" class="alert d-none" role="status"></div><p id="crmManageSummary"></p><input name="action" type="hidden" value="update"><input name="booking_id" id="crmManageId" type="hidden">
 <div class="row g-3"><div class="col-md-6"><label class="form-label" for="crmStage">Workflow status</label><select class="form-select" name="stage" id="crmStage"><?php foreach(['Pending','Assigned','Processing','Confirmed','Completed','Cancelled'] as $stage): ?><option><?= $stage ?></option><?php endforeach ?></select></div>
 <?php if($_SESSION['role']==='admin'): ?><div class="col-md-6"><label class="form-label" for="crmManageAssign">Assigned employee</label><select class="form-select" name="assigned_user_id" id="crmManageAssign"><option value="0">Unassigned</option><?php foreach($workspaceEmployees as $employee): ?><option value="<?= (int)$employee['id'] ?>"><?= htmlspecialchars($employee['username'],ENT_QUOTES,'UTF-8') ?></option><?php endforeach ?></select></div><?php endif ?>
 <div class="col-md-6"><label class="form-label" for="crmManagePaid">Total received</label><input class="form-control" type="number" min="0" step="0.01" name="paid_amount" id="crmManagePaid" required></div>
 <div class="col-12"><label class="form-label" for="crmManageNote">Payment note / reference</label><textarea class="form-control" name="payment_note" id="crmManageNote" maxlength="255"></textarea></div></div>
 <h6 class="mt-4">Booking history</h6><ul id="crmHistory" class="list-group"></ul></div>
 <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Close</button><button class="btn btn-primary" type="submit">Save booking</button></div></form>
</div></div></div>
<script src="<?= htmlspecialchars(site_url('assets/js/booking-workspace.js'),ENT_QUOTES,'UTF-8') ?>" defer></script>
